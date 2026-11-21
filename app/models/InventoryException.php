<?php
declare(strict_types=1);

/** A stock operation was refused for a business reason. The message is translated and safe to show. */
class InventoryException extends RuntimeException
{
    public function __construct(string $message, public string $field = '')
    {
        parent::__construct($message);
    }
}
