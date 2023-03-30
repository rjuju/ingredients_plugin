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

    /**
     * Build a new IngredientList at the given level, storing the given
     * ingredient.
     *
     * @param int $level: the wanted level.  Caller is responsible for passing
     *                    a sane value
     * @param Ingredient $ingredient: the ingredient to store
     * @return IngredientList
     */
    public static function fromIngredient($level, Ingredient $ingredient)
    {
        $obj = new IngredientList($level);
        $obj->addIngredient($level, $ingredient);

        return $obj;
    }

    /**
     * Build a new "dummy" IngredientList
     *
     * A "dummy" ingredient list is used when the provided list is missing some
     * level.  In this case, we need to create the missing level with no
     * amount, no unit and a "??" placeholder description so the user can
     * hopefully fix the ingredient list.
     *
     * @param int $level: the wanted level.  Caller is responsible for passing
     *                    a sane value.
     * @return IngredientList
     */
    public static function makeDummy($level)
    {
        $obj = new IngredientList($level);

        $obj->addIngredient($level, new Ingredient(ING_NO_AMOUNT, '', '??'));

        return $obj;
    }

    /**
     * Add the given Ingredient to the current list at the given level.
     *
     * This method will either append the Ingredient to the list of ingredients
     * if the level is the same.  If not, it will fetch the last ingredient in
     * the list (a list should always have at least one Ingredient, even if
     * it's a dummy one) and try to nest the given IngredientList there.  It
     * will then either append it to its list if it's the same level or keep
     * nesting it.
     *
     * @param int *level: the wanted level
     * @param Ingredient $ingredient: the Ingredient to add
     */
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

    /**
     * Compute an ingredient list total amount and unit.
     *
     * We simply sum up the amount for each ingredient in the list (which can
     * themselves contains lists), and see if all contributing amounts (ie.
     * stricly more than 0) are of the same unit.  If that's the case we return
     * this unit so the caller can display a total amount in that unit,
     * otherwise return the special ING_NO_AMOUNT unit so caller knows the list
     * is made of different amount.
     *
     * @return array(float, string)
     */
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

    /**
     * Generate the html representation of the given ingredient.
     *
     * @return string
     */
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

    /*
     * Add the given Ingredient to the ingredient nest.
     *
     * If the Ingredient isn't nested (ie. doesn't contain a nested
     * IngredientList), create one.
     *
     * If the wanted level is the next level, just append the next level,
     * otherwise create a dummy nested level and ask this level to add the
     * wanted ingredient, which will recurse and take care of finding the
     * correct level.
     *
     * @param int $cur_level: the ingredient's owning list level
     * @param int $wanted_level: the wanted level for the ingredient to nest
     * @param Ingredient $ingredient: the ingredient to nest
     */
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

    /**
     * Compute a single ingredient total amount and unit.
     *
     * Note that an ingredient can be nested, thus composed of multiple
     * sub-ingredient(s) that themselves can be nested too.
     *
     * We give a priority to the direct element weight / unit if present.  This
     * is a bit faster, but more importantly it's also possible that a nested
     * ingredient weight isn't the sum of its nests (e.g. if you add water and
     * cook it).  In such case the upper level should know better the total
     * weight.
     *
     * @return array(float, string)
     */
    public function computeTotalWeight()
    {
        // If the current ingredient has an amount, simply use it whether or
        // not it contains a nested list of ingredients.
        if ($this->amount > 0)
            return array($this->amount, $this->unit);

        // No direct amount, check if we can compute it from nested
        // ingredient(s) if any.
        if ($this->nested !== NULL)
        {
            list($weight, $unit) = $this->nested->computeTotalWeight();

            if ($weight > 0)
                return array($weight, $unit);
        }

        // No luck, just return whatever was stored, caller knows what to do
        // with a zero or ING_NO_AMOUNT amount.
        return array($this->amount, $this->unit);
    }

    /**
     * Generate the html representation of the given ingredient.
     *
     * @return string
     */
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
