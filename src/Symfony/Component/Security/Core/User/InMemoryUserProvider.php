<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * InMemoryUserProvider is a simple non persistent user provider.
 *
 * Useful for testing, demonstration, prototyping, and for simple needs
 * (a backend with a unique admin for instance)
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InMemoryUserProvider implements UserProviderInterface
{
    private $users;

    /**
     * The user array is a hash where the keys are usernames and the values are
     * an array of attributes: 'password', 'enabled', and 'roles'.
     *
     * @param array $users An array of users
     */
    public function __construct(array $users = [])
    {
        foreach ($users as $username => $attributes) {
            $password = $attributes['password'] ?? null;
            $enabled = $attributes['enabled'] ?? true;
            $roles = $attributes['roles'] ?? [];
            $user = new InMemoryUser($username, $password, $roles, $enabled);

            $this->createUser($user);
        }
    }

    /**
     * Adds a new User to the provider.
     *
     * @throws \LogicException
     */
    public function createUser(UserInterface $user)
    {
        if (isset($this->users[strtolower($user->getUsername())])) {
            throw new \LogicException('Another user with the same username already exists.');
        }

        $this->users[strtolower($user->getUsername())] = $user;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername(string $username)
    {
        $user = $this->getUser($username);

        return new InMemoryUser($user->getUsername(), $user->getPassword(), $user->getRoles(), $user->isEnabled());
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof InMemoryUser && !$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        $storedUser = $this->getUser($user->getUsername());

        // @deprecated since Symfony 5.3
        if (User::class === \get_class($user)) {
            if (User::class !== \get_class($storedUser)) {
                $accountNonExpired = true;
                $credentialsNonExpired = $storedUser->getPassword() === $user->getPassword();
                $accountNonLocked = true;
            } else {
                $accountNonExpired = $storedUser->isAccountNonExpired();
                $credentialsNonExpired = $storedUser->isCredentialsNonExpired() && $storedUser->getPassword() === $user->getPassword();
                $accountNonLocked = $storedUser->isAccountNonLocked();
            }

            return new User($storedUser->getUsername(), $storedUser->getPassword(), $storedUser->getRoles(), $storedUser->isEnabled(), $accountNonExpired, $credentialsNonExpired, $accountNonLocked);
        }

        return new InMemoryUser($storedUser->getUsername(), $storedUser->getPassword(), $storedUser->getRoles(), $storedUser->isEnabled());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class)
    {
        // @deprecated since Symfony 5.3
        if (User::class === $class) {
            return true;
        }

        return InMemoryUser::class == $class;
    }

    /**
     * Returns the user by given username.
     *
     * @throws UsernameNotFoundException if user whose given username does not exist
     */
    private function getUser(string $username)/*: InMemoryUser */
    {
        if (!isset($this->users[strtolower($username)])) {
            $ex = new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
            $ex->setUsername($username);

            throw $ex;
        }

        return $this->users[strtolower($username)];
    }
}
