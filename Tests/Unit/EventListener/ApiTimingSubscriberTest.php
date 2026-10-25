<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\EventListener;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use MauticPlugin\MauticPatchesBundle\EventListener\ApiTimingSubscriber;
use MauticPlugin\MauticPatchesBundle\Service\Performance\PerformanceLog;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AllowMockObjectsWithoutExpectations]
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
        $conn   = $this->createStub(Connection::class);
        $conn->method('getConfiguration')->willReturn($config);
        $dir    = sys_get_temp_dir().'/apitiming-'.uniqid();
        $sub    = new ApiTimingSubscriber($conn, new PerformanceLog($dir));
        $kernel = $this->createStub(KernelInterface::class);

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
        $conn   = $this->createStub(Connection::class);
        $conn->method('getConfiguration')->willReturn($config);
        $sub     = new ApiTimingSubscriber($conn, new PerformanceLog(sys_get_temp_dir().'/never'));
        $request = Request::create('/s/dashboard');

        $sub->onRequest(new RequestEvent($this->createMock(KernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        $this->assertNull($config->getSQLLogger());
    }

    /**
     * Runs a request through the three events and returns the Server-Timing header and the line written to the log.
     *
     * @param array<string, mixed> $query
     *
     * @return array{string, array<string, mixed>}
     */
    private function runRequest(string $path, array $query = [], float $startedAgo = 0.0, string $body = '', int $status = 200): array
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('getConfiguration')->willReturn(new Configuration());
        $dir     = sys_get_temp_dir().'/apitiming-'.uniqid();
        $sub     = new ApiTimingSubscriber($conn, new PerformanceLog($dir));
        $kernel  = $this->createStub(KernelInterface::class);
        $request = Request::create($path, 'POST', $query);
        $request->server->set('REQUEST_TIME_FLOAT', microtime(true) - $startedAgo);
        $request->query->replace($query);

        $sub->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $response = new Response($body, $status);
        $sub->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        $sub->onTerminate(new TerminateEvent($kernel, $request, $response));

        $files = glob($dir.'/api-*.log') ?: [];
        $entry = [] === $files ? [] : json_decode(file($files[0])[0], true);
        array_map('unlink', glob($dir.'/*') ?: []);
        @rmdir($dir);

        return [(string) $response->headers->get('Server-Timing'), $entry];
    }

    public function testTheLogLineCarriesWhatWasAskedAndAnswered(): void
    {
        $query = [];
        foreach (range(1, 12) as $i) {
            $query['filter'.$i] = 'secret-value';
        }
        [, $entry] = $this->runRequest('/api/contacts', $query, 0.0, str_repeat('x', 2048), 404);

        $this->assertEqualsWithDelta(time(), $entry['t'], 2);
        $this->assertSame('POST', $entry['m']);
        $this->assertSame(404, $entry['s']);
        $this->assertEquals(2.0, $entry['kb']);
        $this->assertSame(0, $entry['q']);
        $this->assertSame('', $entry['r']);
        $this->assertSame(array_slice(array_keys($query), 0, 10), $entry['qs'], 'names of the first ten filters, never the values');
        $this->assertGreaterThan(0, $entry['mem']);
        $this->assertLessThan(4096, $entry['mem'], 'in MB');
        $this->assertEquals(0.0, $entry['db']);
    }

    public function testASlowRequestListsItsSlowestQueriesAndAFastOneDoesNot(): void
    {
        [, $slow] = $this->runRequest('/api/contacts', [], 2.0);
        [, $fast] = $this->runRequest('/api/contacts');

        $this->assertArrayHasKey('slow', $slow);
        $this->assertSame([], $slow['slow']);
        $this->assertGreaterThanOrEqual(2000, $slow['ms']);
        $this->assertArrayNotHasKey('slow', $fast);
    }

    public function testTheHeaderSplitsTheTimeBetweenTheDatabaseAndTheApplication(): void
    {
        [$header] = $this->runRequest('/api/contacts', [], 1.0);

        $this->assertSame(1, preg_match('/^db;dur=0\.0, app;dur=(\d+\.\d), total;dur=(\d+\.\d), queries;desc="0"$/', $header, $m), $header);
        $this->assertSame($m[2], $m[1], 'with no query the application took all of it');
        $this->assertGreaterThanOrEqual(1000, (float) $m[2]);
    }

    public function testASubRequestGetsNoHeaderAndNothingIsLoggedWithoutMeasure(): void
    {
        $kernel   = $this->createStub(KernelInterface::class);
        $conn     = $this->createStub(Connection::class);
        $conn->method('getConfiguration')->willReturn(new Configuration());
        $dir      = sys_get_temp_dir().'/apitiming-'.uniqid();
        $sub      = new ApiTimingSubscriber($conn, new PerformanceLog($dir));
        $request  = Request::create('/api/contacts');
        $response = new Response();

        $sub->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        $sub->onTerminate(new TerminateEvent($kernel, $request, $response));
        $this->assertFalse($response->headers->has('Server-Timing'), 'never measured');
        $this->assertDirectoryDoesNotExist($dir);

        $sub->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $sub->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response));
        $this->assertFalse($response->headers->has('Server-Timing'), 'a sub request');
    }

    public function testABrokenMeasureStateIsIgnored(): void
    {
        $kernel   = $this->createStub(KernelInterface::class);
        $sub      = new ApiTimingSubscriber($this->createMock(Connection::class), new PerformanceLog(sys_get_temp_dir().'/never'));
        $request  = Request::create('/api/contacts');
        $response = new Response();

        foreach (['text', ['timer' => 'not a timer', 'start' => 1.0], ['start' => 1.0]] as $state) {
            $request->attributes->set('_patches_perf', $state);
            $sub->onResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
            $this->assertFalse($response->headers->has('Server-Timing'));
        }
    }
}
