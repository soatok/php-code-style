<?php
declare(strict_types=1);
namespace Soatok\CodeStyle;

use PhpCsFixer\Config;

class SoatokRules
{
    /**
     * @api
     */
    public static function config(int $lineLength = 120): Config
    {
        return (new Config())
            ->registerCustomFixers([
                new IndentFixer()
            ])
            ->setRules(self::asArray($lineLength));
    }

    /**
     * @api
     */
    public static function asArray(int $lineLength = 120): array
    {
        return [
            '@PSR12' => true,
            'single_import_per_statement' => false,
            'blank_line_after_opening_tag' => false,
            'blank_line_between_import_groups' => false,
            'soatok/wrap_long_import_statements' => ['line_length' => $lineLength],
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
