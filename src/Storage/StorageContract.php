<?php

namespace Jaydeep\LaravelTimeMachine\Storage;

interface StorageContract
{
    /**
     * Persist a request profile and return its id.
     *
     * @return string
     */
    public function store(array $profile);

    /**
     * List stored profiles as lightweight summaries, newest first.
     *
     * @return array<int,array>
     */
    public function all();

    /**
     * Fetch a single full profile by id, or null if it does not exist.
     *
     * @return array|null
     */
    public function find($id);

    /**
     * Remove every stored profile.
     *
     * @return void
     */
    public function clear();
}
