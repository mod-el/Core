<?php namespace Model\Core;

interface ModuleInterface
{
	public function getUrl(?string $controller = null, ?string $id = null, array $tags = [], array $opt = []): ?string;

	public function getController(array $request, string $rule): ?array;
}
