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
    private $variants = []; // array of IngredientList
    private $errors = [];   // array or string
    private string $cur_variant = '';

    // constructor
    function __construct()
    {
        // nothing yet
    }

    // private API
    private function pushIngredient(int $level, Ingredient $ingredient)
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
    public function addVariant(string $name)
    {
        if (array_key_exists($name, $this->variants))
        {
            $this->errors[] = "Variante $name dupliquée";
            return;
        }

        $this->cur_variant = $name;
        $this->variants[$name] = NULL;
    }

    public function addRawIngredient(int $level, float $amount, string $unit,
        string $desc)
    {
        if ($level < 1)
        {
            $this->errors[] = "Ingrédient $desc à un niveau de $level";
            return;
        }

        $ingredient = new Ingredient($amount, $unit, $desc);
        $this->pushIngredient($level, $ingredient);
    }

    public function toHtml(): string
    {
        $out = '';
        $select = '';
        $rand = rand();
        $first_variant = false;

        foreach($this->variants as $name => $variant)
        {
            if ($name != ING_NO_VARIANT)
            {
                if ($select == '')
                {
                    $first_variant = true;
                    $id = "ing_select-$rand";
                    $select = "Variantes : <label for=\"$id\"></label>"
                        . "<select name=\"$id\" id=\"$id\" data-rand=\"$rand\""
                        . ' onchange="ingselectListener(this)">' . "\n";

                    $select .= <<<JS
<script language="javascript">
function ingselectListener(obj){
    var wanted = obj.value;
    var rand = obj.dataset.rand;
    var divs = document.getElementsByClassName("ing_variant-" + rand);

    for (var i = 0; i < divs.length; i++) {
        var listDivId = divs[i].id.slice();

        if (divs[i].id == wanted)
            divs[i].style.display = "block";
        else
            divs[i].style.display = "none";
    }
}
</script>

JS;
                }

                $id = "variant_$name-$rand";
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

                $style .= " border: 1px solid red;";

                $out .= "<div"
                    . " class=\"ing_variant-$rand\" id=\"$id\""
                    . " style=\"$style\"> <!-- variant $name -->\n";
                $out .= "Variante <b>$name</b>\n";
            }
            $out .= $variant->toHtml();

            list($total_weight, $unit) = $variant->computeTotalWeight();
            $out .= "Pour un total de "
                . Ingredient::add_input_box($total_weight)
                . " $unit<br/>\n";

            if ($name != ING_NO_VARIANT)
                $out .= "</div> <!-- variant $name -->\n";
        }

        if ($select != '')
            $select .= "</select><br/>\n";

        return $select . $out;
    }
}
