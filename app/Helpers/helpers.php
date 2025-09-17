<?php

use App\Models\Poem;

function poem_slug(Poem $poem)
{
    return $poem->poem_id . '-' . name2slug($poem->name);
}

function name2slug($name)
{
    return str_replace([' ', '/', '-'], ['', '_', '_'], $name);
}
