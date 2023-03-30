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
!(defined('ING_NAME_PREFIX')) && define('ING_NAME_PREFIX', 'ing_name_');
!(defined('ING_CMD_SET_VARIANT')) && define('ING_CMD_SET_VARIANT', 'set_variant');
!(defined('ING_CMD_SET_TOTAL')) && define('ING_CMD_SET_TOTAL', 'set_total');

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
        $this->Lexer->addEntryPattern('<(?:ingredients|ingrédients).*?>(?=.*?</(?:ingredients|ingrédients)>)',
            $mode, 'plugin_ingredient');
    }

    public function postConnect()
    {
        $this->Lexer->addExitPattern('</(?:ingredients|ingrédients)>', 'plugin_ingredient');
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
                    $recipe = $this->parse_ingredients($match, $renderer);

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
    private function parse_ingredients($match, Doku_Renderer $renderer)
    {
        $recipe = new IngredientRecipe();
        $pattern;

        $list = explode("\n", $match);
        foreach($list as $value)
        {
            // ignore empty lines
            if (trim($value) == '')
                continue;

            // detect variant declaration
            $pattern = "/^\s*(?:option|variant|variante) (.*)\s*$/";
            if (preg_match($pattern, $value, $matches) == 1)
            {
                $recipe->addVariant($matches[1]);
                continue;
            }

            // detect global ingredient list quantity
            $pattern = "/^\s*pour\s+(\d+?)?\s*(.*?)\s*$/";
            if (preg_match($pattern, $value, $matches) == 1)
            {
                $desc = $this->render_desc($matches[2], $renderer);
                $amount = $matches[1];

                $recipe->setOverallQuantity($amount, $desc);
                continue;
            }

            // detect command
            if (strncmp($value, "cmd ", 4) == 0)
            {
                $pattern = "/^cmd\s+\"(.+?)\"\s+(\d+)?\s*(.*?)\s*$/";
                if (preg_match($pattern, $value, $matches) == 1)
                {
                    $id = $matches[1];
                    $nth = $matches[2];
                    $cmd = $renderer->_xmlEntities($matches[3]);

                    $recipe->addCommand($id, $nth, $cmd);
                    continue;
                }
                else
                    $recipe->error("Command \"$value\" invalid");
            }

            // not a special line, it has to be an ingredient
            $pattern = "/^(\s*\*)?\s*(\d+(?:\.\d+)?)?\s*((?:g|gr|ml)\.? )?\s*(.*?)\s*$/";
            $det = preg_match_all($pattern, $value, $matches, PREG_PATTERN_ORDER);

            $level = 1;
            $val = ING_NO_AMOUNT;
            $unit = '';
            $desc = '';

            if (array_key_exists(0, $matches[1]))
                $level = (strlen($matches[1][0]) - 1) / 2;
            else
                $desc = $value;

            if (array_key_exists(0, $matches[2]) && $matches[2][0] != '')
                $val = floatval($matches[2][0]);
            else
                $desc = substr($value, ($level * 2) + 1);
            if (array_key_exists(0, $matches[3]))
                $unit = $matches[3][0];
            if (array_key_exists(0, $matches[4]))
                $desc = $matches[4][0];

            $unit = $this->normalize_unit($unit);
            $desc = $this->render_desc($desc, $renderer);

            $recipe->addRawIngredient($level, $val, $unit, $desc);
        }
        return $recipe;
    }

    /**
     * Normalize the given unit
     * @param string: the raw unit as matched by the regexp
     * @return string with the normalized unit
     */
    private function normalize_unit($unit)
    {
        if ($unit === NULL)
            $unit = '';

        // normalize all grams unit to g
        if (substr($unit, 0, 1) == 'g')
            $unit = 'g';

        // normalize trailing .
        if ($unit != '' && substr($unit, -1, 1) != '.')
            $unit .= ".";

        return $unit;
    }

    /**
     * Escape and apply doku syntax to the given raw description.  There's
     * probably a better way to do that but I have no idea where to look and
     * it's good enough for now
     *
     * @param string: the raw description
     * @param Doku_Renderer: a dokuwiki renderer
     * @return string: the escaped and properly formatted description
     */
    private function render_desc($desc, Doku_Renderer $renderer)
    {
        // first make it safe
        $desc = $renderer->_xmlEntities($desc);
        // replace <del> that have just been escaped
        $desc = preg_replace('/&lt;del&gt;(.*?)&lt;\/del&gt;/', '<del>\1</del>', $desc);
        // replace bolds
        $desc = preg_replace('/\*\*(.*?)\*\*/', '<strong>\1</strong>', $desc);
        // replace ital
        $desc = preg_replace('/\/\/(.*?)\/\//', '<em>\1</em>', $desc);
        // replace underline
        $desc = preg_replace('/__(.*?)__/', '<em class="u">\1</em>', $desc);
        // replace monospaced, with ' possibly replaced by &#039;'
        $desc = preg_replace("/(?:''|&#039;&#039;)(.*?)(?:''|&#039;&#039;)/", '<code>\1</code>', $desc);
        // replace internal links
        $desc = preg_replace_callback("/\[\[((?:.+?:)?.+?)(?:\|(.+?))?\]\]/",
            function ($matches) use ($renderer)
            {
                $name = (count($matches) == 3) ? $matches[2] : null;
                return $renderer->internallink($matches[1], $name, null, true);
            },
            $desc);
        // replace external links
        $desc = preg_replace_callback("/\[\[((?:\w+:\/\/).+?)\|(.+?)\]\]/",
            function ($matches) use ($renderer)
            {
                return $renderer->externallink($matches[1], $matches[2], true);
            },
            $desc);

        return $desc;
    }
}
