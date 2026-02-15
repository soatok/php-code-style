<?php
declare(strict_types=1);
namespace Soatok\CodeStyle;

class SoatokRules
{
    /**
     * @api
     */
    public static function asArray(): array
    {
        return [
            '@PSR12' => true,
            'single_import_per_statement' => false,
            'blank_line_after_opening_tag' => false,
            'blank_line_between_import_groups' => false,
            'blank_lines_before_namespace' => [
                'min_line_breaks' => 1, 'max_line_breaks' => 1
            ],
            'single_line_empty_body' => true,
            'class_definition' => [
                'single_line' => true,
            ],
            'no_empty_statement' => true,
            'array_syntax' => ['syntax' => 'short'],
            'strict_param' => true,
        ];
    }
}
