<?php
declare(strict_types=1);
namespace Soatok\CodeStyle\Tests;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Soatok\CodeStyle\IndentFixer;

final class IndentFixerTest extends TestCase
{
    private IndentFixer $fixer;

    protected function setUp(): void
    {
        $this->fixer = new IndentFixer();
    }

    #[DataProvider('provideFixCases')]
    public function testFix(
        string $expected,
        ?string $input = null,
        int $lineLength = 120
    ): void {
        $this->fixer->configure(['line_length' => $lineLength]);
        $file = new \SplFileInfo(__FILE__);

        $code = $input ?? $expected;
        $tokens = Tokens::fromCode($code);
        $this->fixer->fix($file, $tokens);
        $result = $tokens->generateCode();

        self::assertSame($expected, $result);

        $tokens2 = Tokens::fromCode($result);
        $this->fixer->fix($file, $tokens2);
        self::assertSame(
            $expected,
            $tokens2->generateCode(),
            'Fixer is not idempotent'
        );
    }

    /**
     * @return iterable
     */
    public static function provideFixCases(): iterable
    {
        // Short import — no change
        yield 'short single use function' => [
            "<?php\nuse function foo, bar, baz;\n",
        ];

        // Short import — no change (class)
        yield 'short single use class' => [
            "<?php\nuse Foo, Bar, Baz;\n",
        ];

        // Single import (no comma) — skip entirely
        yield 'single import no comma' => [
            "<?php\nuse function array_key_exists;\n",
        ];

        // Long use function — wrap
        yield 'long use function wraps' => [
            <<<'EXPECTED'
            <?php
            use function array_key_exists,
                explode,
                is_array,
                is_null,
                is_object,
                json_decode,
                json_last_error_msg,
                ltrim,
                parse_url,
                property_exists,
                str_replace,
                str_starts_with,
                substr,
                trim;
            EXPECTED . "\n",
            "<?php\nuse function array_key_exists, explode,"
            . ' is_array, is_null, is_object, json_decode,'
            . ' json_last_error_msg, ltrim, parse_url,'
            . ' property_exists, str_replace,'
            . " str_starts_with, substr, trim;\n",
        ];

        // Long use (class) — wrap at 80 chars
        yield 'long use class wraps' => [
            <<<'EXPECTED'
            <?php
            use Very\Long\NamespaceA\ClassA,
                Very\Long\NamespaceB\ClassB,
                Very\Long\NamespaceC\ClassC,
                Very\Long\NamespaceD\ClassD;
            EXPECTED . "\n",
            '<?php' . "\n"
            . 'use Very\Long\NamespaceA\ClassA,'
            . ' Very\Long\NamespaceB\ClassB,'
            . ' Very\Long\NamespaceC\ClassC,'
            . " Very\\Long\\NamespaceD\\ClassD;\n",
            80,
        ];

        // Already wrapped, fits on one line — collapse
        yield 'wrapped but short collapses' => [
            "<?php\nuse function foo, bar, baz;\n",
            "<?php\nuse function foo,\n    bar,\n    baz;\n",
        ];

        // Already correctly wrapped — no change (at 60 chars)
        yield 'already wrapped long is idempotent' => [
            <<<'EXPECTED'
            <?php
            use function array_key_exists,
                explode,
                is_array,
                is_null,
                is_object;
            EXPECTED . "\n",
            null,
            60,
        ];

        // use const — wrap
        yield 'long use const wraps' => [
            <<<'EXPECTED'
            <?php
            use const VERY_LONG_CONSTANT_A,
                VERY_LONG_CONSTANT_B,
                VERY_LONG_CONSTANT_C,
                VERY_LONG_CONSTANT_D;
            EXPECTED . "\n",
            '<?php' . "\n"
            . 'use const VERY_LONG_CONSTANT_A,'
            . ' VERY_LONG_CONSTANT_B,'
            . ' VERY_LONG_CONSTANT_C,'
            . " VERY_LONG_CONSTANT_D;\n",
            80,
        ];

        // Import with alias
        yield 'import with alias wraps correctly' => [
            <<<'EXPECTED'
            <?php
            use function some_long_function_name as short,
                another_long_function_name as other;
            EXPECTED . "\n",
            '<?php' . "\n"
            . 'use function some_long_function_name as short,'
            . " another_long_function_name as other;\n",
            60,
        ];

        // Custom line length
        yield 'custom short line length' => [
            <<<'EXPECTED'
            <?php
            use function foo,
                bar,
                baz;
            EXPECTED . "\n",
            "<?php\nuse function foo, bar, baz;\n",
            20,
        ];

        // Group imports are skipped
        yield 'group import untouched' => [
            "<?php\nuse Foo\\{Bar, Baz};\n",
        ];
    }
}
