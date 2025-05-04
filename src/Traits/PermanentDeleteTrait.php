<?php

namespace Igniter\ActiveRecord\Traits;

trait PermanentDeleteTrait
{
    public function delete(bool $permanent = true)
    {
        return parent::delete(true);
    }
}
