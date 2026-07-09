<?php

namespace FluentEvents\App\Utils\Vue;

trait Enqueueable
{
	/**
	 * @param callable $callback
	 * @return self
	 */
	public function enqueue(callable $callback): self
	{
		$callback();

		return $this;
	}
}
