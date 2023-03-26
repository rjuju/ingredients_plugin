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
      divs[i].style.display = "block";
    else
      divs[i].style.display = "none";
  }
}

/**
 * Dynamically recompute an ingredient list variant when one amount is changed.
 *
 * To cope with multiple ingredient lists (or event multiple variants), each is
 * generated with a per-variant random number that's postfixed to the classe
 * name.  We can that random number from the input's dataset.
 */
function inginputListener(obj){
  var newval = obj.value;
  var initval = obj.dataset.initval;
  var rand = obj.dataset.rand;
  var divs = document.getElementsByClassName("ing_input-" + rand);
  var ratio;

  ratio = newval * 1.0 / initval;

  for (var i = 0; i < divs.length; i++) {
    // don't process the source object itself, as it already has the wanted
    // value
    if (divs[i] === obj)
      continue;

    // round to 1 decimal without having a .0
    divs[i].value = Math.round(divs[i].dataset.initval * ratio * 10) / 10;
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
    if (totals[i].parentElement.style.display == "none")
      continue;

    cpt += 1;
    if (cpt == nth)
    {
      var input = totals[i];
      input.value = val;
      input.dispatchEvent(new Event('change'));
      return;
    }
  }

  console.log("ERROR: " + totals.length + " ing_total objects with class \""
    + cls + "\" found, but wanted #" + nth);
}

//jQuery(document).ready(function() {
//  ing_commands();
//});
