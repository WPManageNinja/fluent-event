<?php

namespace FluentEvents\App\Hooks\Handlers;

use FluentEvents\App\App;

class CPTHandler
{
	/**
	 * List of custom post type classes to register on `init`.
	 *
	 * Each class should have a `registerPostType()` method (invoked via the
	 * container below) that calls `register_post_type()` with its own args.
	 *
	 * @var array<class-string>
	 */
	protected $customPostTypes = [
		// ExampleCPT::class
	];

	/**
	 * Register every custom post type declared on this handler.
	 *
	 * Wired from app/Hooks/actions.php via
	 * `$app->addAction('init', 'CPTHandler@registerPostTypes')`.
	 *
	 * @return void
	 */
	public function registerPostTypes()
	{
		foreach ($this->customPostTypes as $cpt) {
			App::make($cpt)->registerPostType();
		}
	}
}
