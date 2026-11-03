<?php

namespace FluentEvent\Tests\Unit\Models;

use FluentEvent\App\Http\Requests\CalendarRequest;
use FluentEvent\App\Http\Requests\EventRequest;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Models\Post;
use FluentEvent\App\Models\User;
use FluentEvent\Tests\TestCase;

/**
 * The model-level rules CLAUDE.md states: explicit $fillable, the shared status
 * vocabulary, and defaultMeta() as the record of what the JSON column holds.
 */
class ModelContractTest extends TestCase
{
    // Read-side views of WordPress tables; writes go through wp_insert_post()/wp_insert_user().
    const READ_ONLY_MODELS = [Post::class, User::class];

    public function test_every_written_model_declares_an_explicit_fillable_list()
    {
        foreach ($this->models() as $model) {
            if (in_array($model, self::READ_ONLY_MODELS, true)) {
                continue;
            }

            $fillable = (new $model())->getFillable();

            $this->assertNotEmpty($fillable, "{$model} has no \$fillable");
            $this->assertEmpty(array_intersect($fillable, ['id', 'ID']), "{$model} makes its primary key fillable");
        }
    }

    public function test_the_read_only_models_are_never_written_through_the_orm()
    {
        foreach ($this->sourceFiles() as $path => $source) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:Post|User)::(?:create|insert|query\(\)->(?:create|insert|update))\b/',
                $source,
                "{$path} writes through a read-only model"
            );
        }
    }

    public function test_a_calendar_owner_is_set_server_side_not_mass_assigned()
    {
        $this->assertNotContains('created_by', (new Calendar())->getFillable());
    }

    public function test_each_model_maps_to_its_own_table()
    {
        $this->assertSame('fe_event_details', (new Event())->getTable());
        $this->assertSame('fe_calendars', (new Calendar())->getTable());
        $this->assertSame('fe_guests', (new Guest())->getTable());
        $this->assertSame('fe_meta', (new Meta())->getTable());
    }

    public function test_events_and_calendars_share_one_status_vocabulary()
    {
        $this->assertSame('public,unlisted,private,draft', Event::STATUSES);
        $this->assertSame(Event::STATUSES, Calendar::STATUSES);
        $this->assertSame(explode(',', Event::STATUSES), array_keys(Event::POST_STATUSES));
    }

    /**
     * @dataProvider postStatuses
     */
    public function test_an_event_status_maps_to_the_matching_post_status($status, $postStatus)
    {
        $this->assertSame($postStatus, Event::postStatusFor($status));
    }

    public function postStatuses()
    {
        return [
            'public'   => ['public', 'publish'],
            'unlisted' => ['unlisted', 'publish'],
            'private'  => ['private', 'private'],
            'draft'    => ['draft', 'draft'],
            // An unknown status must never publish.
            'unknown'  => ['archived', 'draft'],
            'empty'    => ['', 'draft'],
        ];
    }

    public function test_the_entry_modes_are_rsvp_open_and_none()
    {
        $this->assertSame('rsvp,open,none', Event::ENTRY_TYPES);
    }

    public function test_every_event_meta_key_the_request_accepts_has_a_default()
    {
        $defaults = array_keys(Event::defaultMeta());
        sort($defaults);

        $accepted = EventRequest::META_KEYS;
        sort($accepted);

        $this->assertSame($defaults, $accepted);
    }

    public function test_every_calendar_meta_key_the_request_accepts_has_a_default()
    {
        $this->assertEmpty(array_diff(CalendarRequest::META_KEYS, array_keys(Calendar::defaultMeta())));
        $this->assertSame(['theme_color' => Calendar::DEFAULT_THEME_COLOR], Calendar::defaultMeta());
    }

    public function test_seat_statuses_are_valid_guest_statuses()
    {
        $guestStatuses = explode(',', Guest::STATUSES);

        $this->assertEmpty(array_diff(Event::SEAT_STATUSES, $guestStatuses));
        $this->assertContains(Guest::DEFAULT_STATUS, $guestStatuses);
    }

    public function test_json_columns_are_cast_to_arrays()
    {
        foreach ([Event::class => 'meta', Calendar::class => 'meta', Guest::class => 'meta', Meta::class => 'value'] as $model => $column) {
            $this->assertSame('array', (new $model())->getCasts()[$column] ?? null, "{$model}::{$column} is not cast to an array");
        }
    }

    /**
     * @return string[]
     */
    private function models()
    {
        $models = [];

        foreach (glob(FLUENT_EVENT_PLUGIN_DIR . 'app/Models/*.php') as $file) {
            $class = 'FluentEvent\\App\\Models\\' . basename($file, '.php');

            if ($class !== 'FluentEvent\\App\\Models\\Model') {
                $models[] = $class;
            }
        }

        $this->assertNotEmpty($models);

        return $models;
    }

    /**
     * @return array<string, string>
     */
    private function sourceFiles()
    {
        $sources = [];

        foreach (['app', 'Modules'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(FLUENT_EVENT_PLUGIN_DIR . $dir));

            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $sources[$file->getPathname()] = file_get_contents($file->getPathname());
                }
            }
        }

        return $sources;
    }
}
