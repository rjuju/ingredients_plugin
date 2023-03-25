<?php

/**
 * Plugin Ingredient: Class to store the full ingredient list
 *
 * @license    FIXME
 * @author     Julien Rouhaud <rjuju123@gmail.com>
 */

class IngredientList
{
    // private attributes
    private $level;
    private $list;

    // constructor
    private function __construct($level)
        //, float $amount, string $unit, string $desc)
    {
        // should not happen
        if ($level < 1)
            $this->level = 1;
        else
            $this->level = $level;
        $list = [];

        return $this;
    }

    // public API
    public static function fromIngredient($level, Ingredient $ingredient)
    {
        $obj = new IngredientList($level);
        $obj->addIngredient($level, $ingredient);

        return $obj;
    }

    public static function makeDummy($level)
    {
        $obj = new IngredientList($level);

        $obj->addIngredient($level, new Ingredient(ING_NO_AMOUNT, '', '??'));

        return $obj;
    }

    public function addIngredient($level, Ingredient $ingredient)
    {
        // if this is the same level, just add it to the array
        if ($level == $this->level)
        {
            $this->list[] = $ingredient;
            return;
        }

        // need to go in nested ingredient.  First find the latest ingredient,
        end($this->list);
        $last = key($this->list);

        // and nest it
        $this->list[$last]->nest($this->level, $level, $ingredient);
    }

    public function computeTotalWeight()
    {
        $total_weight = 0;
        $unit = '';

        foreach($this->list as $ingredient)
        {
            list($ing_weight, $ing_unit) = $ingredient->computeTotalWeight();

            // ignore ingredient with no given amount
            if ($ing_weight == ING_NO_AMOUNT)
                continue;

            if ($ing_weight <= 0)
            {
                return array(0, '');
            }

            $total_weight += $ing_weight;

            // save the new unit if not done yet
            if ($unit == '')
                $unit = $ing_unit;

            // mark unit as invalid if found multiple different units
            if ($ing_unit != '' && $unit != $ing_unit)
                $unit = ING_INVALID_UNIT;
        }

        return array($total_weight, $unit);
    }

    public function toHtml($rand, $class)
    {
        $out = "<ul> <!-- IngredientList level $this->level -->\n";

        foreach($this->list as $ingredient)
        {
            $li_class = "level$this->level";

            if ($ingredient->hasNested())
                $li_class .= " node";

            $out .= "<li class=\"$li_class\">\n";
            $out .= $ingredient->toHtml($rand, $class);
            $out .= "</li>\n";
        }
        $out .= "</ul> <!-- IngredientList level $this->level -->\n";

        return $out;
    }
}

class Ingredient
{
    // private attributes
    private $amount;
    private $unit;
    private $desc;
    private $nested = NULL;

    // constructor
    function __construct($amount, $unit, $desc)
    {
        $this->amount = $amount;
        $this->unit = $unit;
        $this->desc = $desc;
    }

    // public API
    public function getAmount() { return $this->amount; }
    public function getUnit() { return $this->unit; }
    public function getDesc() { return $this->desc; }

    public function hasNested() { return $this->nested !== NULL; }

    public static function add_input_box($value, $rand, $class, $size = NULL)
    {
        if ($size === NULL)
            $size = 5;

        if ($class === NULL)
            $class = '';

        $value = round($value, 1);
        $str = "<input type=\"number\" id=\"ing_input-$rand\""
            . " maxlength=\"$size \""
            . ' style="width: ' . (12 * $size) . 'px"'
            . " class=\"ing_input ing_input-$rand $class\" data-rand=\"$rand\""
            . " value=\"$value\" data-initval=\"$value\""
            . " onchange=\"inginputListener(this)\"> ";

        return $str;
    }

    public function nest($cur_level, $wanted_level,
        Ingredient $ingredient)
    {
        // Start a new nesting for the current IngredientList
        if ($this->nested === NULL)
        {
            $nextlevel = ($cur_level + 1);

            if ($wanted_level != $nextlevel)
            {
                // If the wanted level isn't the next level, create a dummy
                // intermediate level.  We'll be later adding the ingredient to
                // it, which will be automatically pushed down as needed.
                $obj = IngredientList::makeDummy($nextlevel);
                $this->nested = $obj;
            }
            else
            {
                // Otherwise just create the new level using the given
                // ingredient and we're done!
                $obj = IngredientList::fromIngredient($nextlevel, $ingredient);
                $this->nested = $obj;
                return;
            }
        }

        $this->nested->addIngredient($wanted_level, $ingredient);
    }

    public function computeTotalWeight()
    {
        if ($this->nested !== NULL)
        {
            list($weight, $unit) = $this->nested->computeTotalWeight();

            if ($weight > 0)
                return array($weight, $unit);
        }

        return array($this->amount, $this->unit);
    }

    public function toHtml($rand, $class)
    {
        $content = '';

        if ($this->amount > 0)
            $content .= Ingredient::add_input_box($this->amount, $rand, NULL);

        if ($this->unit != '')
            $content .= "$this->unit ";

        $content .= $this->desc;

        if ($this->nested !== NULL)
            $content .= $this->nested->toHtml($rand, $class);

        return "<div class=\"li\">$content</div>\n";
    }
}
