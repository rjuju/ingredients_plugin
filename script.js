/**
 * Javascript functionality for the ingredient plugin
 */

/**
 * Display the correct div when changing a ingredient variant select.
 *
 * To cope with multiple ingredient variant selects, each is generated with a
 * global (as in per-ingredient-list rather than per-variant) random number
 * that's postfixed to the class name.  We get that global random number from
 * the select's dataset.
 */
function ingselectListener(obj){
  var wanted = obj.value;
  var globrand = obj.dataset.globrand;
  var divs = document.getElementsByClassName("ing_variant-" + globrand);

  for (var i = 0; i < divs.length; i++) {
    if (divs[i].id == wanted)
      divs[i].classList.remove('ing_hidden');
    else
      divs[i].classList.add('ing_hidden');
  }
}

/**
 * Dynamically recompute an ingredient list variant when one amount is changed.
 *
 * To cope with multiple ingredient lists (or event multiple variants), each is
 * generated with a per-variant random number that's postfixed to the classe
 * name.  We can that random number from the input's dataset.
 *
 * obj is the "ing_input" element that was changed.
 * if reset_initval is true, we also modify all object data-initval to the
 * computed new value.
 */
function inginputListener(obj, reset_initval){
  var newval = obj.value;
  var initval = obj.dataset.initval;
  var rand = obj.dataset.rand;
  var divs = document.getElementsByClassName("ing_input-" + rand);
  var ratio;

  ratio = newval * 1.0 / initval;

  for (var i = 0; i < divs.length; i++) {
    // round to 1 decimal without having a .0
    var setval = Math.round(divs[i].dataset.initval * ratio * 10) / 10;

    // if reset_initval is true update data-initval, even for the source object
    // itself.
    if (reset_initval)
      divs[i].dataset.initval = setval;

    // don't process the source object itself, as it already has the wanted
    // value.
    if (divs[i] === obj)
      continue;

    // for any other object, simply update the value.
    divs[i].value = setval;
  }
}

function ing_select(cls, nth, val)
{
  var selects = document.getElementsByClassName("ing_select " + cls);

  if (selects.length == 0) {
    console.log("ERROR: no ing_select with class \"" + cls + "\" found");
    return;
  }

  if (selects.length < nth) {
    console.log("ERROR: " + selects.length + " ing_select objects with class \""
      + cls + "\" found, but wanted #" + nth);
    return;
  }

  var select = selects[nth - 1];
  var options = select.options;

  for (var i = 0; i < options.length; i++) {
    if (options[i].text == val) {
      select.value = options[i].value;
      select.dispatchEvent(new Event('change'));
      return;
    }
  }

  console.log("ERROR: no option \"" + val + "\" found for ing_select \""
    + cls + "\" #" + nth + ":");
  console.log(select);
}

/**
 * Set the given value as the ingredient list total for the nth occurence of
 * ingredient list with HTML class cls.
 *
 * Note that this will trigger the ing_set_total event, which also set the
 * data-initvol property, so the given ingredient list will behave as if it was
 * originally written for that total amount.
 *
 * Note also that all variants (if any) of the given ingredient list will be
 * updated, so a user can still change the variant and keep a consistent
 * amount.
 */
function ing_set_total(cls, nth, val)
{
  var totals = document.getElementsByClassName("ing_total " + cls);

  if (totals.length == 0) {
    console.log("ERROR: no ing_total with class \"" + cls + "\" found");
    return;
  }

  // iterate over all found objects.  We must ignore the hidden ones, but that
  // property is known in its parent div
  var cpt = 0;
  for (var i = 0; i < totals.length; i++)
  {
    if (totals[i].parentElement.classList.contains("ing_hidden"))
      continue;

    cpt += 1;

    // we found the wanted ingredient list.  Update all variants in case the
    // default one is not the one the user want.
    if (cpt == nth)
    {
      var foundcls = totals[i].parentElement.className;
      var divs = document.getElementsByClassName(foundcls);

      for (var j = 0; j < divs.length; j++)
      {
        var div = divs[j];
        var input = div.getElementsByClassName('ing_total')[0];

        input.value = val;
        input.dispatchEvent(new Event('ing_set_total'));
      }
      return;
    }
  }

  console.log("ERROR: " + totals.length + " ing_total objects with class \""
    + cls + "\" found, but wanted #" + nth);
}

/**
 * Event listener when choosing a mold type
 *
 * Create the needed number of inputs for each dimension of the selected mold
 * type.
 *
 * select is the html select element that triggered the event.
 */
function choose_mold_type(select)
{
  var mold_type = select.value;
  var span = select.parentElement.getElementsByTagName('span')[0];
  var nb_dim = 0;
  var html = document.createElement('span');

  switch (mold_type)
  {
    case 'cercle':
      nb_dim = 2;
      break;
    case 'carré':
      nb_dim = 2;
      break;
    case 'rectangulaire':
      nb_dim = 3;
      break;
    default:
      alert("Type de moule " + mold_types + "inconnu !");
      break;
  }

  for (var i = 0; i < nb_dim; i++)
  {
    if (i != 0)
    {
      if (i < (nb_dim - 1))
        html.append(document.createTextNode(" x "));
      else
        html.append(document.createTextNode(" (base) - "));
    }

    var input = document.createElement('input');
    input.classList.add('ing_input');
    html.append(input);
  }

  // last dimension is always height
  html.append(document.createTextNode(' (h) cm '));

  span.replaceWith(html);
}

/**
 * Add a new mold line in a custom mold list.
 *
 * Make visible all elements that are by default hidden in a mold list and add
 * a new default li item to the ul list in the given mold list.
 *
 * id is the div id in which the ul list is contained.
 */
function add_mold(id, id_apply)
{
  var div = document.getElementById(id);
  var apply = document.getElementById(id_apply);
  var ul = div.getElementsByTagName('ul')[0];
  var li = document.createElement("li");
  var mold_types = ['cercle', 'carré', 'rectangulaire'];

  // show the div containing the user's molds and the "apply" button
  div.classList.remove('ing_hidden');
  apply.classList.remove('ing_hidden');
  div.style.display = 'block';
  apply.style.display = 'inline';

  // create a select element to choose the type of mold
  var select = document.createElement("select");
  select.onchange = function() { choose_mold_type(this); };
  select.options[0] = new Option('Type de moule', '');
  select.options[0].enabled = false;
  for (var i = 0; i < mold_types.length; i++)
    select.options[i + 1] = new Option(mold_types[i], mold_types[i]);
  li.appendChild(select);

  // add a button for the number of mold for this type, by default 1
  var nb = document.createElement("input");
  nb.type = "number";
  nb.classList.add("mold-nb");
  nb.value = 1;
  li.appendChild(nb);
  li.appendChild(document.createTextNode(' moule(s) - '));

  // add an empty span that will contain the mold dimensions when user chooses
  // a mold type
  li.appendChild(document.createElement("span"));

  // finally add the remove button
  var btn = document.createElement("input");
  btn.type = "button";
  btn.onclick = function() { this.parentElement.remove(); };
  btn.value = "\u2613";
  li.appendChild(btn);

  ul.appendChild(li);
}

/**
 * Update all ingredient list for the user's list of mold
 *
 * Compute the volume of all custom molds, and adapt all ingredient list with a
 * ratio based on the original recipe's mold(s).
 */
function apply_molds(id, ori)
{
  var ul = document.getElementById(id);
  var lis = ul.childNodes;
  var initvol = ul.dataset.initvol;
  var volume = 0;
  var ratio;

  if (lis.length == 0)
  {
    alert('Il faut ajouter un moule !');
    return;
  }

  for (var i = 0; i < lis.length; i++)
  {
    var li = lis[i];
    var mold_type = li.firstChild.value;
    var nbel = li.getElementsByClassName('mold-nb')[0];
    var inputs = li.getElementsByTagName('span')[0].getElementsByTagName('input');
    var dims = [];
    var nb;
    var height;
    var base;

    if (li.firstChild.value == '')
    {
      alert('Il faut choisir un type de moule !');
      return;
    }

    if (nbel.value == '')
    {
      alert('Il faut choisir un nombre de moule !');
      return;
    }

    nb = parseFloat(nbel.value);

    if (nb <= 0)
    {
      alert('Nombre de moule invalide !');
      return;
    }

    for (var j = 0; j < inputs.length; j++)
    {
      var val;

      if (inputs[j].value == '')
      {
        alert('Il faut indiquer une taille de moule !');
        return;
      }

      val = parseFloat(inputs[j].value);

      if (val <= 0)
      {
        alert('Taille de moule invalide !');
        return;
      }

      dims.push(val);
    }

    height = dims.pop();

    switch (mold_type)
    {
      case 'cercle':
        base = dims[0] / 2 * 3.1415 * 3.1415;
        break;
      case 'carré':
        base = dims[0] * dims[0];
        break;
      case 'rectangulaire':
        base = dims[0] * dims[1];
        break;
      default:
        alert("Type de moule " + mold_types + "inconnu !");
        break;
    }
    volume += (nb * base * height);
  }

  ratio = volume * 1.0 / initvol;

  // finaly, apply that ratio to every ingredient list
  var ing_totals = document.getElementsByClassName('ing_total');
  for (var i = 0; i < ing_totals.length; i++)
  {
    var ing_total = ing_totals[i];
    var value = ing_total.dataset.initval;

    // round to 1 decimal without having a .0
    ing_total.value = Math.round(value * ratio * 10) / 10;
    ing_total.dispatchEvent(new Event('change'));
  }
}

/**
 * Entry point of custom code.  We first register new event listeners, and
 * then trigger custom commands by raising an ing_command event.
 */
jQuery(document).ready(function() {
  jQuery(".ing_total").each(function() {
    jQuery(this).on("ing_set_total", function() {
      inginputListener(this, true);
    });
  });
  document.dispatchEvent(new Event('ing_command'));
});
