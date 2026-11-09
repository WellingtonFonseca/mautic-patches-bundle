<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use MauticPlugin\MauticPatchesBundle\EventListener\ContactPreferencesStayOnListSubscriber;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

#[AllowMockObjectsWithoutExpectations]
class ContactPreferencesStayOnListSubscriberTest extends TestCase
{
    private const VIEW = ['closeModal' => 1, 'newContent' => 'CONTACT PAGE', 'route' => '/s/contacts/view/9'];

    /** @var list<Request> */
    private array $subRequests = [];

    private LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function subscriber(): ContactPreferencesStayOnListSubscriber
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/s/contacts');

        return new ContactPreferencesStayOnListSubscriber($router, $this->logger);
    }

    private function save(string $referer, string $method = 'POST', string $action = 'contactFrequency', bool $session = true): Request
    {
        $request = Request::create('http://mautic.test/s/contacts/contactFrequency/9', $method);
        $request->headers->set('Referer', $referer);
        $request->attributes->set('_route', 'mautic_contact_action');
        $request->attributes->set('objectAction', $action);
        if ($session) {
            $request->setSession(new Session(new MockArraySessionStorage()));
        }

        return $request;
    }

    private function answerTo(Request $request, array $answer = self::VIEW, int $type = HttpKernelInterface::MAIN_REQUEST, int $listStatus = 200): JsonResponse
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(function (Request $sub, int $kind) use ($listStatus) {
            $this->subRequests[] = $sub;
            $this->assertSame(HttpKernelInterface::SUB_REQUEST, $kind);

            return new JsonResponse(['newContent' => 'THE LIST', 'route' => '/s/contacts'], $listStatus);
        });
        $response = new JsonResponse($answer);
        $this->subscriber()->onResponse(new ResponseEvent($kernel, $request, $type, $response));

        return $response;
    }

    public function testSubscribesToTheResponse(): void
    {
        $this->assertSame(['onResponse', 0], ContactPreferencesStayOnListSubscriber::getSubscribedEvents()[KernelEvents::RESPONSE]);
    }

    public function testSwapsTheContactPageForTheListKeepingTheSearchOfTheUser(): void
    {
        $response = $this->answerTo($this->save('http://mautic.test/s/contacts/2?search=ana'));

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('THE LIST', $data['newContent']);
        $this->assertSame('/s/contacts', $data['route']);
        $this->assertSame(1, $data['closeModal']);
        $this->assertTrue($data['updateMainContent']);
        $this->assertCount(1, $this->subRequests);
        $this->assertSame('/s/contacts/2', $this->subRequests[0]->getPathInfo());
        $this->assertSame('ana', $this->subRequests[0]->query->get('search'));
        $this->assertSame('XMLHttpRequest', $this->subRequests[0]->headers->get('X-Requested-With'));
        $this->assertNotNull($this->subRequests[0]->getSession());
    }

    #[DataProvider('untouched')]
    public function testLeavesCoresAnswerAlone(Request $request, array $answer, int $type): void
    {
        $response = $this->answerTo($request, $answer, $type);

        $this->assertSame($answer, json_decode((string) $response->getContent(), true));
        $this->assertSame([], $this->subRequests);
    }

    public static function untouched(): iterable
    {
        $self = new self('x');

        yield 'save from the contact page' => [$self->save('http://mautic.test/s/contacts/view/9'), self::VIEW, HttpKernelInterface::MAIN_REQUEST];
        yield 'no referer' => [$self->save(''), self::VIEW, HttpKernelInterface::MAIN_REQUEST];
        yield 'form with errors' => [$self->save('http://mautic.test/s/contacts'), ['newContent' => 'FORM'], HttpKernelInterface::MAIN_REQUEST];
        yield 'opening the modal (GET)' => [$self->save('http://mautic.test/s/contacts', 'GET'), self::VIEW, HttpKernelInterface::MAIN_REQUEST];
        yield 'another action' => [$self->save('http://mautic.test/s/contacts', 'POST', 'email'), self::VIEW, HttpKernelInterface::MAIN_REQUEST];
        yield 'no session' => [$self->save('http://mautic.test/s/contacts', 'POST', 'contactFrequency', false), self::VIEW, HttpKernelInterface::MAIN_REQUEST];
        yield 'sub request' => [$self->save('http://mautic.test/s/contacts'), self::VIEW, HttpKernelInterface::SUB_REQUEST];
    }

    public function testKeepsCoresAnswerAndLogsWhenTheListCannotBeRead(): void
    {
        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('could not be read'), ['url' => 'http://mautic.test/s/contacts', 'status' => 500]);
        $response = $this->answerTo($this->save('http://mautic.test/s/contacts'), self::VIEW, HttpKernelInterface::MAIN_REQUEST, 500);

        $this->assertSame(self::VIEW, json_decode((string) $response->getContent(), true));
    }
}
