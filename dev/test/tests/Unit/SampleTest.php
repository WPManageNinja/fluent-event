<?php

namespace Dev\Test\Tests\Unit;

use Dev\Test\Inc\TestCase;
use Dev\Test\Inc\RefreshDatabase;
use FluentEvents\App\Models\User;

/**
 * Unit-level samples: demonstrate the plugin's factory + role-switching
 * helpers without depending on post/comment seeding (which the minimal
 * scaffold intentionally doesn't ship).
 */
class SampleTest extends TestCase
{
    use RefreshDatabase;

    public function testUserFactoryCreatesUsers()
    {
        $initial = User::count();

        $this->factory->user->count(3)->create();

        $this->assertSame($initial + 3, User::count());
    }

    public function testEditorContext()
    {
        $this->actLike('editor');

        $this->assertTrue(current_user_can('edit_posts'));
    }

    public function testAdminContext()
    {
        $this->actLikeAdmin();

        $this->assertTrue(current_user_can('administrator'));
    }
}
