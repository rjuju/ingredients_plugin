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
    private $commands = []; // array of IngredientCommand
    private $errors = [];   // array or string
    private $cur_variant = '';
    private $mold_ratio = []; // array of (mold type, nb mold, dimensions)
    private $mold_type_dim = [
        'cercle' => [
            'dim' => 2,
        ],
        'rond' => [
            'alias' => 'cercle',
        ],
        'carre' => [
            'dim' => 2,
        ],
        'rectangulaire' => [
            'dim' => 3,
        ],
        'rectangle' => [
            'alias' => 'rectangulaire',
        ],
    ];

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

    private function descToHtmlIdent($desc)
    {
        $id = $desc;
        $id = preg_replace('/[\s]/','_', $id);
        $id = preg_replace('/[^[A-Za-z0-9_]]/','', $id);

        return $id;
    }

    private function generateJsCommand($id, $nth, $action, $payload)
    {
        $ret = NULL;

        if ($action == ING_CMD_SET_VARIANT)
        {
            $val = $payload[0];

            $ret = "ing_select('ing_name_$id', $nth, '$val');";
        }
        else if ($action == ING_CMD_SET_TOTAL)
        {
            $val = $payload[0];

            $ret = "ing_set_total('ing_name_$id', $nth, '$val')\n";
        }

        if ($ret !== NULL)
            return "$ret\n";

        $this->errors[] = "Commande $action invalide";
        return '';
    }

    // public API
    public function error($detail)
    {
        $this->errors[] = $detail;
    }

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

        // normalize non-provided amount to NULL
        if ($amount == '')
            $amount = NULL;

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

    public function addMoldRatio($type, $nb, $dimensions)
    {
        if (!array_key_exists($type, $this->mold_type_dim))
        {
            $this->error("Type de moule \"$type\" inconnu");
            return;
        }

        if (array_key_exists('alias', $this->mold_type_dim[$type]))
        {
            $type = $this->mold_type_dim[$type]['alias'];
        }

        if (count($dimensions) != $this->mold_type_dim[$type]['dim'])
        {
            $wanted = $this->mold_type_dim[$type][$dim];
            $this->error("il faut $wanted dimensions pour un moule de type "
                . "$type, "
                . count($dimensions) . "trouvée(s) (" . $matches[2]. ")");
            return;
        }

        $this->mold_ratio[] = [$type, $nb, $dimensions];
    }

    public function addCommand($id, $nth, $cmd)
    {
        $id = $this->descToHtmlIdent($id);

        if ($nth === NULL || $nth == '')
            $nth = 1;

        $pattern = "/^\s*variante\s+(.*)\s*$/";
        if (preg_match($pattern, $cmd, $matches) == 1)
        {
            $action = ING_CMD_SET_VARIANT;
            $target = $matches[1];

            $this->commands[] = array($id, $nth, $action, array($target));
            return;
        }

        $pattern = "/^\s*total\s+(\d+?(?:\.\d+)?)\s*$/";
        if (preg_match($pattern, $cmd, $matches) == 1)
        {
            $action = ING_CMD_SET_TOTAL;
            $amount = $matches[1];

            $this->commands[] = array($id, $nth, $action, array($amount));
            return;
        }

        $error = "commande \"$cmd\" pour l'identifiant \"$id\"";
        if ($nth !== NULL)
            $error .= " (n° $nth)";
        $error .= " invalide<br/>"
            . "Commandes supportées:<br/>"
            . '- cmd "identifiant" X variante nom_variante<br/>'
            . '- cmd "identifiant" X total X.Y<br/>';
        $this->errors[] = $error;
    }

    /**
     * Generate the html representation of the given ingredient.
     *
     * @return string
     */
    public function toHtml()
    {
        $errors = '';
        $mold_ratios = '';
        $out = '';
        $select = '';
        $glob_rand = rand();
        $rand;
        $first_variant = false;
        $class = '';

        foreach($this->errors as $error)
            $errors .= "<b>ERREUR</b>: $error<br/>\n";

        foreach($this->mold_ratio as $line)
        {
            list($type, $nb, $dimensions) = $line;
            $rand = rand();
            $volume = 0;

            switch ($type)
            {
                case 'cercle':
                    $volume = $dimensions[0] / 2 * 3.1415 * 3.1415;
                    $volume *= $dimensions[1];
                    break;
                case 'carré':
                    $volume = $dimensions[0] * $dimensions[°];
                    $volume *= $dimensions[1];
                    break;
                case 'rectangulaire':
                    $volume = $dimensions[0] * $dimensions[1];
                    $volume *= $dimensions[2];
                    break;
            }

            $volume *= $nb;

            $mold_ratios .= "Recette prévue pour "
                . "<b>$nb</b> "
                . ($nb > 1 ? "moules" : "moule") . " de type "
                . "<b>" . $type . "</b> - "
                . implode('x', $dimensions)
                . " cm\n";

            $mold_ratios .= "<div class=\"ing_hidden\" id=\"mold-$rand\">"
                . "Mes moules:\n"
                . "<ul id=\"mold-ul-$rand\" data-initvol=\"$volume\"></ul>"
                . "</div>"
                ;

            $mold_ratios .= "<button onclick=\"add_mold('mold-$rand', 'mold-apply-$rand')\">"
                . 'Ajouter un moule utilisé'
                . '</button> '
                . "<button class=\"ing_hidden\" id=\"mold-apply-$rand\" "
                . "onclick=\"apply_molds('mold-ul-$rand', 'ing_input-$rand')\">"
                .  "Appliquer</button>"
                ;
        }

        foreach($this->variants as $name => $variant)
        {
            foreach($this->overall as list($amount, $desc))
            {
                if ($class != '')
                    $class .= ' ';

                $class .= ING_NAME_PREFIX . $this->descToHtmlIdent($desc);
            }

            $rand = rand();
            if ($name != ING_NO_VARIANT)
            {
                if ($select == '')
                {
                    $first_variant = true;
                    $id = "ing_select-$glob_rand";
                    $select = "<div>";
                    $select .= "Variantes : <label for=\"$id\"></label>"
                        . "<select name=\"$id\" id=\"$id\""
                        . " class=\"ing_select $class\""
                        . " data-globrand=\"$glob_rand\""
                        . ' onchange="ingselectListener(this)">' . "\n";
                }

                $id = "variant_" . $this->descToHtmlIdent($name). "-$glob_rand";

                $select .= "<option value=\"$id\">$name</option>\n";

                $style = "";

                if ($first_variant)
                    $first_variant = false;
                else
                    $style = "ing_hidden";

                $out .= "<div"
                    . " class=\"ing_variant-$glob_rand $style\" id=\"$id\""
                    . "<!-- variant $name -->\n"
                    . "Variante <b>$name</b>\n";
            }
            $out .= $variant->toHtml($rand, $class);

            $out .= "Pour un total de ";

            // handle user-defined overall total amount (e.g. X cakes)
            if (count($this->overall) > 0)
            {
                foreach($this->overall as list($amount, $desc))
                {
                    $o_class = $class;
                    if ($o_class != '')
                        $o_class .= " ";
                    $o_class .= "ing_overall ";
                    $o_class .= "ing_overall_" . $this->descToHtmlIdent($desc);

                    if ($amount !== NULL)
                    {
                        $out .= Ingredient::add_input_box($amount, $rand,
                            $o_class);
                        $out .= " $desc, ";
                    }
                }
            }

            // handle automatic total weight
            list($total_weight, $unit) = $variant->computeTotalWeight();
            $t_class = "ing_total $class";
            $out .= Ingredient::add_input_box($total_weight, $rand, $t_class)
                . " $unit";

            // if there's a single overall declaration without unit, use this
            // name for the automatic total weight
            if (count($this->overall) == 1 && $this->overall[0][0] === NULL)
                $out .= " de " . $this->overall[0][1];

            $out .= "<br/>\n";

            if ($name != ING_NO_VARIANT)
                $out .= "</div> <!-- variant $name -->\n";
        }

        if ($select != '')
            $select .= "</select></div>\n";

        // if any command was created, call them on document ready
        $cmds = '';
        foreach($this->commands as $array)
        {
            list($id, $nth, $action, $payload) = $array;

            $cmds .= $this->generateJsCommand($id, $nth, $action, $payload);
        }

        if ($cmds != '')
        {
            // We use a custom event to ensure appropiate order of execution
            $cmds = "\n<script language=\"javascript\">\n"
                . "document.addEventListener(\"ing_command\", () => {\n"
                . $cmds
                . "\n});\n"
                . "</script>\n";
        }

        // return everything
        return $errors . $mold_ratios . $select . $out . $cmds;
    }
}
