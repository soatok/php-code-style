<?php
declare(strict_types=1);
namespace Soatok\CodeStyle;

use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\FixerConfiguration\{
    FixerConfigurationResolver,
    FixerConfigurationResolverInterface,
    FixerOptionBuilder
};
use PhpCsFixer\FixerDefinition\{
    CodeSample,
    FixerDefinition,
    FixerDefinitionInterface
};
use PhpCsFixer\Tokenizer\{
    CT,
    Token,
    Tokens
};
use SplFileInfo;
use const T_CLOSE_TAG, T_NAMESPACE, T_USE;

final class IndentFixer
    implements ConfigurableFixerInterface
{
    /** @var array{line_length: int} */
    private array $configuration;

    private FixerConfigurationResolverInterface $configDef;

    public function __construct(array $configuration = [])
    {
        $this->configure($configuration);
    }

    public function getName(): string
    {
        return 'Soatok/wrap_long_import_statements';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        $long = '<?php' . "\n"
            . 'use function array_key_exists, explode,'
            . ' is_array, is_null, is_object, json_decode,'
            . ' json_last_error_msg, ltrim, parse_url,'
            . ' property_exists, str_replace,'
            . " str_starts_with, substr, trim;\n";

        return new FixerDefinition(
            'Wrap comma-separated import statements that'
            . ' exceed the line length limit.',
            [new CodeSample($long, ['line_length' => 80])],
        );
    }

    /**
     * Must run after SingleImportPerStatementFixer (1).
     * Must run before OrderedImportsFixer (-30).
     */
    public function getPriority(): int
    {
        return -1;
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function supports(SplFileInfo $file): bool
    {
        return true;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_USE);
    }

    public function configure(array $configuration): void
    {
        $this->configuration = $this
            ->getConfigurationDefinition()
            ->resolve($configuration);
    }

    public function getConfigurationDefinition(
    ): FixerConfigurationResolverInterface {
        if (!isset($this->configDef)) {
            $this->configDef = new FixerConfigurationResolver([
                (new FixerOptionBuilder(
                    'line_length',
                    'Maximum line length before wrapping.'
                ))
                    ->setAllowedTypes(['int'])
                    ->setDefault(80)
                    ->getOption(),
            ]);
        }
        return $this->configDef;
    }

    public function fix(SplFileInfo $file, Tokens $tokens): void
    {
        if ($tokens->count() === 0
            || !$this->isCandidate($tokens)
        ) {
            return;
        }

        $limit = $this->configuration['line_length'];

        $indexes = $this->findImportUseIndexes($tokens);
        foreach (array_reverse($indexes) as $index) {
            $endIndex = $tokens->getNextTokenOfKind(
                $index,
                [';', [T_CLOSE_TAG]]
            );

            // Skip group imports — use Foo\{Bar, Baz}
            $prev = $tokens->getPrevMeaningfulToken(
                $endIndex
            );
            if ($tokens[$prev]->isGivenKind(
                CT::T_GROUP_IMPORT_BRACE_CLOSE
            )) {
                continue;
            }

            // Skip single imports (no commas to wrap on)
            if (!$this->hasComma($tokens, $index, $endIndex)) {
                continue;
            }

            $indent = $this->detectIndent($tokens, $index);
            $oneLine = $this->buildSingleLine(
                $tokens,
                $index,
                $endIndex
            );

            if (mb_strlen($indent . $oneLine) <= $limit) {
                $this->collapseToSingleLine(
                    $tokens,
                    $index,
                    $endIndex
                );
            } else {
                $this->wrapAtCommas(
                    $tokens,
                    $index,
                    $endIndex,
                    $indent
                );
            }
        }
    }

    /**
     * Collect indexes of all import `use` statements.
     *
     * By the time fixers run, the tokenizer has already transformed trait-use → CT::T_USE_TRAIT and
     * closure-use → CT::T_USE_LAMBDA, so plain T_USE tokens are exclusively import statements.
     *
     * @return list<int>
     */
    private function findImportUseIndexes(
        Tokens $tokens
    ): array {
        $indexes = [];
        $count = $tokens->count();

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if ($token->isGivenKind(T_NAMESPACE)) {
                $next = $tokens->getNextTokenOfKind(
                    $i,
                    [';', '{']
                );
                if ($tokens[$next]->equals('{')) {
                    $i = $next;
                }
                continue;
            }

            if ($token->isGivenKind(T_USE)) {
                $indexes[] = $i;
            }
        }

        return $indexes;
    }

    /**
     * Detect the indentation of the line containing
     * the token at $index.
     */
    private function detectIndent(
        Tokens $tokens,
        int $index
    ): string {
        while (true) {
            $wsIndex = $tokens->getPrevTokenOfKind(
                $index,
                [[\T_WHITESPACE]]
            );
            if ($wsIndex === null) {
                return '';
            }

            $content = $tokens[$wsIndex]->getContent();
            if (str_contains($content, "\n")) {
                $lines = explode("\n", $content);
                return end($lines);
            }

            $prev = $tokens[$wsIndex - 1];
            if ($prev->isGivenKind([\T_OPEN_TAG, \T_COMMENT])
                && str_ends_with(
                    $prev->getContent(),
                    "\n"
                )
            ) {
                $lines = explode("\n", $content);
                return end($lines);
            }

            $index = $wsIndex;
        }
    }

    private function hasComma(
        Tokens $tokens,
        int $start,
        int $end
    ): bool {
        for ($i = $start + 1; $i < $end; ++$i) {
            if ($tokens[$i]->equals(',')) {
                return true;
            }
        }
        return false;
    }

    private function buildSingleLine(
        Tokens $tokens,
        int $start,
        int $end
    ): string {
        $result = '';
        for ($i = $start; $i <= $end; ++$i) {
            if ($tokens[$i]->isWhitespace()) {
                if ($result !== ''
                    && !str_ends_with($result, ' ')
                ) {
                    $result .= ' ';
                }
                continue;
            }
            $result .= $tokens[$i]->getContent();
        }
        return $result;
    }

    private function collapseToSingleLine(
        Tokens $tokens,
        int $start,
        int $end
    ): void {
        for ($i = $start + 1; $i < $end; ++$i) {
            if (!$tokens[$i]->isWhitespace()) {
                continue;
            }
            if ($tokens[$i]->getContent() !== ' ') {
                $tokens[$i] = new Token(
                    [\T_WHITESPACE, ' ']
                );
            }
        }
    }

    private function wrapAtCommas(
        Tokens $tokens,
        int $start,
        int $end,
        string $indent
    ): void {
        $cont = "\n" . $indent . '    ';

        // Walk backwards so insertions don't shift
        // earlier indices
        for ($i = $end - 1; $i > $start; --$i) {
            if (!$tokens[$i]->equals(',')) {
                continue;
            }

            $next = $i + 1;
            if ($next <= $end
                && $tokens[$next]->isWhitespace()
            ) {
                if ($tokens[$next]->getContent() !== $cont) {
                    $tokens[$next] = new Token(
                        [\T_WHITESPACE, $cont]
                    );
                }
            } else {
                $tokens->insertAt(
                    $next,
                    new Token([\T_WHITESPACE, $cont])
                );
                ++$end;
            }
        }

        for ($i = $start + 1; $i < $end; ++$i) {
            if ($tokens[$i]->equals(',')) {
                break;
            }
            if ($tokens[$i]->isWhitespace()
                && str_contains(
                    $tokens[$i]->getContent(),
                    "\n"
                )
            ) {
                $tokens[$i] = new Token(
                    [\T_WHITESPACE, ' ']
                );
            }
        }
    }
}
