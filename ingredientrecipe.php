<?php

/**
 * Plugin Ingredient: Class to store the full recipe's ingredients
 *
 * @license    FIXME
 * @author     Julien Rouhaud <rjuju123@gmail.com>
 */

require_once('ingredientlist.php');

class IngredientRecipe
{
    // private attributes
    private $overall = [];  // array of (amount, desc)
    private $variants = []; // array of IngredientList
    private $errors = [];   // array or string
    private $cur_variant = '';

    // constructor
    function __construct()
    {
        // nothing yet
    }

    // private API
    private function pushIngredient($level, Ingredient $ingredient)
    {
        // create a default top-level variant if needed
        if (count($this->variants) == 0)
            $this->addVariant(ING_NO_VARIANT);

        // handle the first ingredient in the current variant
        if ($this->variants[$this->cur_variant] === NULL)
        {
            // normal case, just create an IngredientRecipe and we're done
            if ($level == 1)
            {
                $obj = IngredientList::fromIngredient($level, $ingredient);
                $this->variants[$this->cur_variant] = $obj;
                return;
            }

            // missing some level, create dummy one, and fallback to adding an
            // ingredient to it
            $obj = IngredientList::makeDummy(1);
            $this->variants[$this->cur_variant] = $obj;
        }

        $this->variants[$this->cur_variant]->addIngredient($level, $ingredient);
    }

    // public API
    public function addVariant($name)
    {
        if (array_key_exists($name, $this->variants))
        {
            $this->errors[] = "Variante $name dupliquée";
            return;
        }

        $this->cur_variant = $name;
        $this->variants[$name] = NULL;
    }

    public function setOverallQuantity($amount, $desc)
    {
        if (count($this->variants) != 0)
            $this->errors[] = "Quantité globale \"$amount $desc\" déclarée"
                . " après des variantes et/ou ingrédients";
        $this->overall[] = [$amount, $desc];
    }

    public function addRawIngredient($level, $amount, $unit, $desc)
    {
        if ($level < 1)
        {
            $this->errors[] = "Ingrédient $desc à un niveau de $level";
            return;
        }

        $ingredient = new Ingredient($amount, $unit, $desc);
        $this->pushIngredient($level, $ingredient);
    }

    public function toHtml()
    {
        $errors = '';
        $out = '';
        $select = '';
        $glob_rand = rand();
        $rand;
        $first_variant = false;

        foreach($this->errors as $error)
            $errors .= "<b>ERREUR</b>: $error<br/>\n";

        foreach($this->variants as $name => $variant)
        {
            $rand = rand();
            if ($name != ING_NO_VARIANT)
            {
                if ($select == '')
                {
                    $first_variant = true;
                    $id = "ing_select-$glob_rand";
                    $select = "<div class=\"ing_select\">";
                    $select .= "Variantes : <label for=\"$id\"></label>"
                        . "<select name=\"$id\" id=\"$id\""
                        . " data-globrand=\"$glob_rand\""
                        . ' onchange="ingselectListener(this)">' . "\n";
                }

                $id = "variant_$name-$glob_rand";
                $id = preg_replace('/[\s]/','_', $id);
                $id = preg_replace('/[^[A-Za-z0-9_]]/','', $id);

                $select .= "<option value=\"$id\">$name</option>\n";

                $style = "";

                if ($first_variant)
                {
                    $first_variant = false;
                    $style = "display: block;";
                }
                else
                    $style = "display: none;";

                $out .= "<div"
                    . " class=\"ing_variant-$glob_rand\" id=\"$id\""
                    . " style=\"$style\"> <!-- variant $name -->\n";
                $out .= "Variante <b>$name</b>\n";
            }
            $out .= $variant->toHtml($rand);

            $out .= "Pour un total de ";

            // handle user-defined overall total amount (e.g. X cakes)
            if (count($this->overall) > 0)
            {
                foreach($this->overall as list($amount, $desc))
                {
                    $out .= Ingredient::add_input_box($amount, $rand);
                    $out .= " $desc, ";
                }
            }

            // handle automatic total weight
            list($total_weight, $unit) = $variant->computeTotalWeight();
            $out .= Ingredient::add_input_box($total_weight, $rand)
                . " $unit<br/>\n";

            if ($name != ING_NO_VARIANT)
                $out .= "</div> <!-- variant $name -->\n";
        }

        if ($select != '')
            $select .= "</select></div>\n";

        return $errors . $select . $out;
    }
}
