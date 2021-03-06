<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Fixer\ControlStructure;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 * @author Gregor Harlan <gharlan@web.de>
 */
final class NoUnneededControlParenthesesFixer extends AbstractFixer implements ConfigurableFixerInterface
{
    /**
     * @var string[]
     */
    private static $defaultConfiguration = array(
        'break',
        'clone',
        'continue',
        'echo_print',
        'return',
        'switch_case',
        'yield',
    );

    /**
     * @var string[] List of statements to fix
     */
    private $controlStatements;

    private static $loops = array(
        'break' => array('lookupTokens' => T_BREAK, 'neededSuccessors' => array(';')),
        'clone' => array('lookupTokens' => T_CLONE, 'neededSuccessors' => array(';', ':', ',', ')'), 'forbiddenContents' => array('?', ':')),
        'continue' => array('lookupTokens' => T_CONTINUE, 'neededSuccessors' => array(';')),
        'echo_print' => array('lookupTokens' => array(T_ECHO, T_PRINT), 'neededSuccessors' => array(';', array(T_CLOSE_TAG))),
        'return' => array('lookupTokens' => T_RETURN, 'neededSuccessors' => array(';', array(T_CLOSE_TAG))),
        'switch_case' => array('lookupTokens' => T_CASE, 'neededSuccessors' => array(';', ':')),
    );

    /**
     * Dynamic yield option set on constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // To be moved back to compile time property declaration when PHP support of PHP CS Fixer will be 5.5+
        if (defined('T_YIELD')) {
            self::$loops['yield'] = array('lookupTokens' => T_YIELD, 'neededSuccessors' => array(';', ')'));
        }
    }

    /**
     * @param array $controlStatements
     */
    public function configure(array $controlStatements = null)
    {
        if (null === $controlStatements) {
            $this->controlStatements = self::$defaultConfiguration;

            return;
        }

        $this->controlStatements = $controlStatements;
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        $types = array();

        foreach (self::$loops as $loop) {
            $types[] = (array) $loop['lookupTokens'];
        }
        $types = call_user_func_array('array_merge', $types);

        return $tokens->isAnyTokenKindsFound($types);
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        // Checks if specific statements are set and uses them in this case.
        $loops = array_intersect_key(self::$loops, array_flip($this->controlStatements));

        foreach ($tokens as $index => $token) {
            if (!$token->equals('(')) {
                continue;
            }

            $blockStartIndex = $index;
            $index = $tokens->getPrevMeaningfulToken($index);
            $token = $tokens[$index];

            foreach ($loops as $loop) {
                if (!$token->isGivenKind($loop['lookupTokens'])) {
                    continue;
                }

                $blockEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $blockStartIndex);
                $blockEndNextIndex = $tokens->getNextMeaningfulToken($blockEndIndex);

                if (!$tokens[$blockEndNextIndex]->equalsAny($loop['neededSuccessors'])) {
                    continue;
                }

                if (array_key_exists('forbiddenContents', $loop)) {
                    $forbiddenTokenIndex = $tokens->getNextTokenOfKind($blockStartIndex, $loop['forbiddenContents']);
                    // A forbidden token is found and is inside the parenthesis.
                    if (null !== $forbiddenTokenIndex && $forbiddenTokenIndex < $blockEndIndex) {
                        continue;
                    }
                }

                if ($tokens[$blockStartIndex - 1]->isWhitespace() || $tokens[$blockStartIndex - 1]->isComment()) {
                    $tokens->clearTokenAndMergeSurroundingWhitespace($blockStartIndex);
                } else {
                    // Adds a space to prevent broken code like `return2`.
                    $tokens->overrideAt($blockStartIndex, array(T_WHITESPACE, ' '));
                }

                $tokens->clearTokenAndMergeSurroundingWhitespace($blockEndIndex);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Removes unneeded parentheses around control statements.',
            array(
                new CodeSample(
                    '<?php
while ($x) { while ($y) { break (2); } }
clone($a);
while ($y) { continue (2); }
echo("foo");
print("foo");
return (1 + 2);
switch ($a) { case($x); }
yield(2);
'
                ),
                new CodeSample(
                    '<?php
while ($x) { while ($y) { break (2); } }
clone($a);
while ($y) { continue (2); }
echo("foo");
print("foo");
return (1 + 2);
switch ($a) { case($x); }
yield(2);
',
                    array('break', 'continue')
                ),
            ),
            null,
            'List of strings which control structures should be modified.',
            self::$defaultConfiguration
        );
    }

    /**
     * Should be run before no_trailing_whitespace.
     *
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 30;
    }
}
