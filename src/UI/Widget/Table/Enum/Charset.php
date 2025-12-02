<?php

declare(strict_types=1);

namespace Seaman\UI\Widget\Table\Enum;

enum Charset: string
{
    case Square = 'square';
    case Rounded = 'rounded';
    case Double = 'double';
    case Heavy = 'heavy';

    /**
     * @return array<string, string>
     */
    public function elements(): array
    {
        $data = file_get_contents(__DIR__ . '/../charsets.json');
        if (!is_string($data)) {
            return [];
        }
        /**
         * @var array<string, array<string,string>> $charsets
         */
        $charsets = json_decode($data, true);
        return $charsets[$this->value];
    }
}
