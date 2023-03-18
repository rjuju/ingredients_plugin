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
    private int $level;
    private $list;

    // constructor
    private function __construct(int $level)
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
    public static function fromIngredient(int $level, Ingredient $ingredient)
    {
        $obj = new IngredientList($level);
        $obj->addIngredient($level, $ingredient);

        return $obj;
    }

    public static function makeDummy(int $level): IngredientList
    {
        $obj = new IngredientList($level);

        $obj->addIngredient(new Ingredient(ING_NO_AMOUNT, '', '??'));

        return $obj;
    }

    public function addIngredient(int $level, Ingredient $ingredient)
    {
        // if this is the same level, just add it to the array
        if ($level == $this->level)
        {
            $this->list[] = $ingredient;
            return;
        }

        // need to go in nested ingredient.  First find the latest ingredient,
        $last = array_key_last($this->list);

        // and nest it
        $this->list[$last]->nest($this->level, $level, $ingredient);
    }

    public function computeTotalWeight(): array
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

    public function toHtml(): string
    {
        $out = "<ul> <!-- IngredientList level $this->level -->\n";

        foreach($this->list as $ingredient)
        {
            $class = "level$this->level";

            if ($ingredient->hasNested())
                $class .= " node";

            $out .= "<li class=\"$class\">\n";
            $out .= $ingredient->toHtml();
            $out .= "</li>\n";
        }
        $out .= "</ul> <!-- IngredientList level $this->level -->\n";

        return $out;
    }
}

class Ingredient
{
    // private attributes
    private float $amount;
    private string $unit;
    private string $desc;
    private $nested = NULL;

    // constructor
    function __construct(float $amount, string $unit, string $desc)
    {
        $this->amount = $amount;
        $this->unit = $unit;
        $this->desc = $desc;
    }

    // public API
    public function getAmount(): float { return $this->amount; }
    public function getUnit(): string { return $this->unit; }
    public function getDesc(): string { return $this->desc; }

    public function hasNested(): bool { return $this->nested !== NULL; }

    public static function add_input_box(float $value, int $size = 5)
    {
        $value = round($value, 1);
        $str = "<input type=\"number\" maxlength=\"$size \""
            . ' style="width: ' . (12 * $size) . 'px"'
            . " value=\"$value\"> ";

        return $str;
    }

    public function nest(int $cur_level, int $wanted_level,
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

    public function computeTotalWeight(): array
    {
        if ($this->nested !== NULL)
        {
            list($weight, $unit) = $this->nested->computeTotalWeight();

            if ($weight > 0)
                return array($weight, $unit);
        }

        return array($this->amount, $this->unit);
    }

    public function toHtml(): string
    {
        $content = '';

        if ($this->amount > 0)
            $content .= Ingredient::add_input_box($this->amount);

        if ($this->unit != '')
            $content .= "$this->unit ";

        $content .= $this->desc;

        if ($this->nested !== NULL)
            $content .= $this->nested->toHtml();

        return "<div class=\"li\">$content</div>\n";
    }
}
