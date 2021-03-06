<?php

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\HttpBasicAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Tests\Authenticator\Fixtures\PasswordUpgraderProvider;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class HttpBasicAuthenticatorTest extends TestCase
{
    private $userProvider;
    private $hasherFactory;
    private $hasher;
    private $authenticator;

    protected function setUp(): void
    {
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->hasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $this->hasher = $this->createMock(PasswordHasherInterface::class);
        $this->hasherFactory
            ->expects($this->any())
            ->method('getPasswordHasher')
            ->willReturn($this->hasher);

        $this->authenticator = new HttpBasicAuthenticator('test', $this->userProvider);
    }

    public function testExtractCredentialsAndUserFromRequest()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $this->userProvider
            ->expects($this->any())
            ->method('loadUserByUsername')
            ->with('TheUsername')
            ->willReturn($user = new InMemoryUser('TheUsername', 'ThePassword'));

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals('ThePassword', $passport->getBadge(PasswordCredentials::class)->getPassword());

        $this->assertSame($user, $passport->getUser());
    }

    /**
     * @dataProvider provideMissingHttpBasicServerParameters
     */
    public function testHttpBasicServerParametersMissing(array $serverParameters)
    {
        $request = new Request([], [], [], [], [], $serverParameters);

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function provideMissingHttpBasicServerParameters()
    {
        return [
            [[]],
            [['PHP_AUTH_PW' => 'ThePassword']],
        ];
    }

    public function testUpgradePassword()
    {
        $request = new Request([], [], [], [], [], [
            'PHP_AUTH_USER' => 'TheUsername',
            'PHP_AUTH_PW' => 'ThePassword',
        ]);

        $this->userProvider = $this->createMock(PasswordUpgraderProvider::class);
        $this->userProvider->expects($this->any())->method('loadUserByUsername')->willReturn(new InMemoryUser('test', 's$cr$t'));
        $authenticator = new HttpBasicAuthenticator('test', $this->userProvider);

        $passport = $authenticator->authenticate($request);
        $this->assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $this->assertEquals('ThePassword', $badge->getAndErasePlaintextPassword());
    }
}
