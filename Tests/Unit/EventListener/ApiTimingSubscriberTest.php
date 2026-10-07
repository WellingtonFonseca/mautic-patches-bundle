<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use MauticPlugin\MauticPatchesBundle\EventListener\ApiTimingSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceLog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ApiTimingSubscriberTest extends TestCase
{
    public function testNormalizesNumericIds(): void
    {
        $this->assertSame('/api/contacts/:id/notes', ApiTimingSubscriber::normalizePath('/api/contacts/15/notes'));
        $this->assertSame('/api/customObjects/:id/items', ApiTimingSubscriber::normalizePath('/api/customObjects/3/items'));
        $this->assertSame('/api/contacts', ApiTimingSubscriber::normalizePath('/api/contacts'));
    }

    public function testOnlyApiPathsAreMeasured(): void
    {
        $this->assertTrue(ApiTimingSubscriber::isApi('/api/contacts'));
        $this->assertFalse(ApiTimingSubscriber::isApi('/s/dashboard'));
        $this->assertFalse(ApiTimingSubscriber::isApi('/apix'));
    }

    public function testAnApiRequestGetsTheServerTimingHeaderAndALogLine(): void
    {
        $config = new Configuration();
        $conn   = $this->createMock(Connection::class);
        $conn->method('getConfiguration')->willReturn($config);
        $dir    = sys_get_temp_dir().'/apitiming-'.uniqid();
        $sub    = new ApiTimingSubscriber($conn, new PerformanceLog($dir));
        $kernel = $this->createMock(KernelInterface::class);

        $request = Request::create('/api/contacts/7');
        $request->attributes->set('_route', 'mautic_api_contacts_getone');

        $sub->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $this->assertNotNull($config->getSQLLogger());

        $response = new Response('', 200);
        $sub->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        $this->assertMatchesRegularExpression('/^db;dur=[\d.]+, app;dur=[\d.]+, total;dur=[\d.]+, queries;desc="0"$/', $response->headers->get('Server-Timing'));

        $sub->onTerminate(new TerminateEvent($kernel, $request, $response));
        $lines = file(glob($dir.'/api-*.log')[0]);
        $entry = json_decode($lines[0], true);
        $this->assertSame('/api/contacts/:id', $entry['p']);
        $this->assertSame('mautic_api_contacts_getone', $entry['r']);

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
    }

    public function testNonApiRequestsAreLeftAlone(): void
    {
        $config = new Configuration();
        $conn   = $this->createMock(Connection::class);
        $conn->method('getConfiguration')->willReturn($config);
        $sub     = new ApiTimingSubscriber($conn, new PerformanceLog(sys_get_temp_dir().'/never'));
        $request = Request::create('/s/dashboard');

        $sub->onRequest(new RequestEvent($this->createMock(KernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertNull($config->getSQLLogger());
    }
}
