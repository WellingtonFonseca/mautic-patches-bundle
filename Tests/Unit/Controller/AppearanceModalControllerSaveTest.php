<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Controller;

use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\MauticPatchesBundle\Controller\AppearanceModalController;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * saveAction only uses what it is handed, so the controller (which extends core's CommonController, heavy to build) is created
 * without its constructor.
 */
#[AllowMockObjectsWithoutExpectations]
class AppearanceModalControllerSaveTest extends TestCase
{
    private UserModel&MockObject $userModel;

    private AppearanceModalController $controller;

    protected function setUp(): void
    {
        $this->userModel  = $this->createMock(UserModel::class);
        $this->controller = (new \ReflectionClass(AppearanceModalController::class))->newInstanceWithoutConstructor();
    }

    /** @return iterable<string, array{string}> */
    public static function themes(): iterable
    {
        foreach (['light', 'dark', 'solarized-light', 'solarized-dark', 'freire'] as $theme) {
            yield $theme => [$theme];
        }
    }

    #[DataProvider('themes')]
    public function testAKnownThemeIsKeptAsThePreference(string $theme): void
    {
        $this->userModel->expects($this->once())->method('setPreference')->with('theme', $theme);

        $response = $this->controller->saveAction(new Request([], ['theme' => $theme]), $this->userModel);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"success":true}', $response->getContent());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function refused(): iterable
    {
        yield 'no theme'        => [[]];
        yield 'empty'           => [['theme' => '']];
        yield 'unknown'         => [['theme' => 'neon']];
        yield 'another case'    => [['theme' => 'Dark']];
        yield 'a longer value'  => [['theme' => 'dark ']];
        yield 'an array'        => [['theme' => ['dark']]];
    }

    /** @param array<string, mixed> $post */
    #[DataProvider('refused')]
    public function testAnythingElseIsRefusedAndNothingIsSaved(array $post): void
    {
        $this->userModel->expects($this->never())->method('setPreference');

        $response = $this->controller->saveAction(new Request([], $post), $this->userModel);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"success":false}', $response->getContent());
    }
}
