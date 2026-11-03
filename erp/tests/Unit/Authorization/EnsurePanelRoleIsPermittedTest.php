<?php

namespace Tests\Unit\Authorization;

use App\Http\Middleware\EnsurePanelRoleIsPermitted;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Behaviour documented in the middleware's own docblock: nobody may stand
 * inside an administrator's `/{role}` URL prefix unless they are an
 * administrator. Database-free: the user is a Mockery double.
 */
class EnsurePanelRoleIsPermittedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // abort() resolves through the application container.
        new Application(dirname(__DIR__, 3));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function request(string $segment, $user): Request
    {
        $request = Request::create("/{$segment}/dashboard");
        $route = new Route('GET', '{role}/dashboard', fn () => null);
        $route->bind($request);
        $route->setParameter('role', $segment);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function user(bool $canViewAll, array $roles)
    {
        $user = Mockery::mock();
        $user->shouldReceive('can')->with('view all users')->andReturn($canViewAll);
        $user->shouldReceive('getRoleNames')->andReturn(collect($roles));

        return $user;
    }

    private function run_(Request $request): int
    {
        return (new EnsurePanelRoleIsPermitted)
            ->handle($request, fn () => new Response('ok'))
            ->getStatusCode();
    }

    public function test_employee_is_refused_under_the_super_admin_prefix(): void
    {
        $this->expectException(HttpException::class);
        $this->run_($this->request('super-admin', $this->user(false, ['employee'])));
    }

    public function test_employee_is_refused_under_the_admin_prefix(): void
    {
        $this->expectException(HttpException::class);
        $this->run_($this->request('admin', $this->user(false, ['employee'])));
    }

    public function test_the_refusal_is_a_403(): void
    {
        try {
            $this->run_($this->request('super-admin', $this->user(false, ['employee'])));
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_holder_of_view_all_users_may_use_any_admin_prefix(): void
    {
        $this->assertSame(200, $this->run_($this->request('admin', $this->user(true, ['Super Admin']))));
    }

    public function test_holding_the_role_itself_is_enough(): void
    {
        $this->assertSame(200, $this->run_($this->request('admin', $this->user(false, ['Admin']))));
    }

    public function test_ordinary_segments_are_untouched(): void
    {
        // HR legitimately opens /employee/user -- must not be blocked.
        $this->assertSame(200, $this->run_($this->request('employee', $this->user(false, ['hr']))));
    }

    public function test_guest_is_left_for_the_auth_middleware(): void
    {
        $this->assertSame(200, $this->run_($this->request('super-admin', null)));
    }
}
