<?php

/**
 * Plugin Ingredient: Ingredient list with dynamic ratio calculation
 *
 * @license    FIXME
 * @author     Julien Rouhaud <rjuju123@gmail.com>
 */

// must be run within DokuWiki
if(!defined('DOKU_INC')) die();

!(defined('ING_NO_VARIANT')) && define('ING_NO_VARIANT', '-');
!(defined('ING_INVALID_UNIT')) && define('ING_INVALID_UNIT', '-');
!(defined('ING_NO_AMOUNT')) && define('ING_NO_AMOUNT', -1);

require_once('ingredientrecipe.php');
require_once('ingredientlist.php');

class syntax_plugin_ingredient extends DokuWiki_Syntax_Plugin
{
    /**
     * What kind of syntax are we?
     */
    public function getType()
    {
        return 'formatting';
    }

    /**
     * What about paragraphs?
     */
    public function getPType()
    {
        return 'normal';
    }

    /**
     * Where to sort in?
     */
    public function getSort()
    {
        return 303;
    }

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode)
    {
        $this->Lexer->addEntryPattern('<ingredients.*?>(?=.*?</ingredients>)',
            $mode, 'plugin_ingredient');
    }

    public function postConnect()
    {
        $this->Lexer->addExitPattern('</ingredients>', 'plugin_ingredient');
    }

    /**
     * Handle the match
     *
     * @param string $match The text matched by the patterns
     * @param int $state The lexer state for the match
     * @param int $pos The character position of the matched text
     * @param Doku_Handler $handler The Doku_Handler object
     * @return  array Return an array with all data you want to use in render
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        return array($state, $match);
    }

    /**
     * Create output
     *
     * @param string $format string     output format being rendered
     * @param Doku_Renderer $renderer the current renderer object
     * @param array $data data created by handler()
     * @return  boolean                 rendered correctly?
     */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format == 'xhtml') {
            /** @var Doku_Renderer_xhtml $renderer */
            // FIXME
            list($state, $match) = $data;
            switch ($state)
            {
                case DOKU_LEXER_ENTER:
                    break;
                case DOKU_LEXER_UNMATCHED :
                    $recipe = $this->parse_ingredients($match);

                    $rendered = $recipe->toHtml();

                    $renderer->doc .= $rendered;
                    break;
                case DOKU_LEXER_EXIT :
                    break;
            }
            return true;
        }
        return false;
    }

    /**
     * Parse the ingredients content
     *
     * @param string: a single line contained between <ingredients> and
     *                </ingredients>
     * @return array of (kind, array of (detail))
     */
    protected function parse_ingredients($match)
    {
        $recipe = new IngredientRecipe();
        $pattern = "/(\s*\*)?\s*(\d+(?:\.\d+)?)\s*((?:g|gr|ml)\.?)?\s*(.*)\s*/";

        $list = explode("\n", $match);
        foreach($list as $value)
        {
            // ignore empty lines
            if (trim($value) == '')
                continue;

            if (preg_match("/^\s*option (.*)\s*$/", $value, $matches) == 1)
            {
                $recipe->addVariant($matches[1]);
                continue;
            }

            $det = preg_match_all($pattern, $value, $matches, PREG_PATTERN_ORDER);

            $level = 1;
            $val = ING_NO_AMOUNT;
            $unit = '';
            $desc = '';

            if (array_key_exists(0, $matches[1]))
                $level = (strlen($matches[1][0]) - 1) / 2;
            else
                $desc = $value;

            if (array_key_exists(0, $matches[2]))
                $val = floatval($matches[2][0]);
            else
                $desc = substr($value, ($level * 2) + 1);
            if (array_key_exists(0, $matches[3]))
                $unit = $matches[3][0];
            if (array_key_exists(0, $matches[4]))
                $desc = $matches[4][0];

            if ($unit === NULL)
                $unit = '';

            // normalize grams unit to g
            if (substr($unit, 0, 1) == 'g')
                $unit = 'g';

            // normalize trailing .
            if ($unit != '' && substr($unit, -1, 1) != '.')
                $unit .= ".";

            $recipe->addRawIngredient($level, $val, $unit, $desc);
        }
        return $recipe;
    }
}
