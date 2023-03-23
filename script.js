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
