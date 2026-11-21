<?php
/* Copyright (C) 2026		Edgar Bustamante	<ebustamantep@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    productplus/lib/productplus.lib.php
 * \ingroup productplus
 * \brief   Common functions of ProductPlus
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function productplusAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("productplus@productplus");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/productplus/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'productplus@productplus');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'productplus@productplus', 'remove');

	return $head;
}

/**
 * Search products by ref, label or barcode.
 * An exact ref or barcode (typed or scanned) wins over the partial matches.
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$search	Text typed or scanned
 * @param	int		$limit	Max number of rows
 * @return	array{rows:array<int,object>,more:bool}	Rows found (rowid, ref, label, barcode, price, price_ttc, price_base_type, stock, tosell, tobuy, fk_product_type) and true if there are more than $limit
 */
function productplus_search_products($db, $search, $limit = 100)
{
	$search = trim($search);
	if ($search === '') {
		return array('rows' => array(), 'more' => false);
	}

	$select = "SELECT p.rowid, p.ref, p.label, p.barcode, p.price, p.price_ttc, p.price_base_type, p.stock, p.tosell, p.tobuy, p.fk_product_type";
	$select .= " FROM ".$db->prefix()."product as p";
	$select .= " WHERE p.entity IN (".getEntity('product').")";

	// Exact ref or barcode first: a barcode scanner must open the product at once
	$sql = $select." AND (p.ref = '".$db->escape($search)."' OR p.barcode = '".$db->escape($search)."')";
	$rows = productplus_fetch_rows($db, $sql.$db->plimit(2));
	if (count($rows) == 1) {
		return array('rows' => $rows, 'more' => false);
	}

	$sql = $select.natural_search(array('p.ref', 'p.label', 'p.barcode'), $search);
	$sql .= $db->order('p.ref', 'ASC');
	$rows = productplus_fetch_rows($db, $sql.$db->plimit($limit + 1));
	$more = (count($rows) > $limit);
	if ($more) {
		array_pop($rows);
	}

	return array('rows' => $rows, 'more' => $more);
}

/**
 * Run a query and return all its rows
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$sql	Query
 * @return	array<int,object>
 */
function productplus_fetch_rows($db, $sql)
{
	$rows = array();
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('productplus: '.$db->lasterror(), LOG_ERR);
		return $rows;
	}
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
	}
	$db->free($resql);

	return $rows;
}

/**
 * Split the value of a VAT select (load_tva) into rate, NPR flag, VAT code and local taxes.
 * The value can be '16', '16*', '16 (CODE)' or '16* (CODE)'.
 *
 * @param	string	$vattxt	Posted value
 * @param	Societe	$mysoc	Own company
 * @return	array{rate:float,npr:int,code:string,localtaxes:array<string,mixed>}
 */
function productplus_parse_vat($vattxt, $mysoc)
{
	$code = '';
	$reg = array();
	if (preg_match('/\((.*)\)/', $vattxt, $reg)) {
		$code = $reg[1];
	}
	$rate = (float) price2num(preg_replace('/\*/', '', preg_replace('/\s*\(.*\)/', '', $vattxt)));
	$npr = preg_match('/\*/', $vattxt) ? 1 : 0;

	$localtaxes = array();
	$tmp = getLocalTaxesFromRate($vattxt, 0, $mysoc, $mysoc);
	if (is_array($tmp) && count($tmp) >= 4) {
		$localtaxes = array('0' => $tmp[0], '1' => $tmp[1], '2' => $tmp[2], '3' => $tmp[3]);
	}

	return array('rate' => $rate, 'npr' => $npr, 'code' => $code, 'localtaxes' => $localtaxes);
}

/**
 * Number typed by the user (1.234,56 or 1234.56), checked before price2num() that turns any text into 0
 *
 * @param	string	$raw	Posted text
 * @param	string	$mode	Rounding mode of price2num() ('MU', 'MS'...)
 * @return	string|false|null	Number, false if it is not a number, null if empty
 */
function productplus_num($raw, $mode = 'MU')
{
	$raw = trim((string) $raw);
	if ($raw === '') {
		return null;
	}
	if (!preg_match('/^[+-]?[0-9\s.,\x{00A0}\x{202F}]+$/u', $raw) || !preg_match('/[0-9]/', $raw)) {
		return false;
	}
	$num = price2num($raw, $mode);

	return is_numeric($num) ? (string) $num : false;
}

/**
 * Quantity for display, without useless decimals (96, 2,5)
 *
 * @param	float|string|null	$qty	Quantity
 * @return	string
 */
function productplus_qty($qty)
{
	global $langs;

	return price(price2num((float) $qty, 'MS'), 0, $langs, 0, 0);
}

/**
 * Translated messages of a failed save of an object
 *
 * @param	CommonObject	$object	Object
 * @return	string[]
 */
function productplus_errors($object)
{
	global $langs;

	$langs->load('errors');
	$messages = array();
	if (!empty($object->error)) {
		$messages[] = $langs->trans($object->error);
	}
	if (!empty($object->errors) && is_array($object->errors)) {
		foreach ($object->errors as $err) {
			$messages[] = $langs->trans($err);
		}
	}
	$messages = array_values(array_unique(array_filter($messages)));
	if (empty($messages)) {
		$messages[] = $langs->trans('Error');
	}

	return $messages;
}

/**
 * Label of a field of the card with its pencil "Modify" (like the native card)
 *
 * @param	string	$label		Translated label
 * @param	string	$editaction	Action that opens the edit form (ex: 'editlabel')
 * @param	bool	$showpencil	False while the field is being edited
 * @param	int		$id			Id of the product
 * @param	string	$moreparam	More url parameters (ex: '&level=2')
 * @return	string				HTML
 */
function productplus_field_label($label, $editaction, $showpencil, $id, $moreparam = '')
{
	global $langs;

	$out = '<table class="nobordernopadding centpercent"><tr><td class="nowrap">'.$label.'</td>';
	if ($showpencil) {
		$url = $_SERVER['PHP_SELF'].'?id='.((int) $id).'&action='.urlencode($editaction).$moreparam.'&token='.newToken();
		$out .= '<td class="right"><a class="editfielda reposition" href="'.dolPrintHTMLForAttribute($url).'">'.img_edit($langs->trans('Modify'), 1).'</a></td>';
	}
	$out .= '</tr></table>';

	return $out;
}

/**
 * Open the inline edit form of a field
 *
 * @param	int		$id			Id of the product
 * @param	string	$setaction	Action that saves (ex: 'setlabel')
 * @param	array<string,int|string>	$hidden	More hidden fields
 * @return	string				HTML
 */
function productplus_form_start($id, $setaction, $hidden = array())
{
	$out = '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $id).'">';
	$out .= '<input type="hidden" name="token" value="'.newToken().'">';
	$out .= '<input type="hidden" name="action" value="'.dolPrintHTMLForAttribute($setaction).'">';
	$out .= '<input type="hidden" name="id" value="'.((int) $id).'">';
	foreach ($hidden as $key => $val) {
		$out .= '<input type="hidden" name="'.dolPrintHTMLForAttribute($key).'" value="'.dolPrintHTMLForAttribute((string) $val).'">';
	}

	return $out;
}

/**
 * Save and Cancel buttons, then close the inline edit form
 *
 * @return	string	HTML
 */
function productplus_form_end()
{
	global $langs;

	$out = ' <input type="submit" class="button smallpaddingimp nomargintop nomarginbottom" name="save" value="'.dolPrintHTMLForAttribute($langs->trans('Save')).'">';
	$out .= ' <input type="submit" class="button button-cancel smallpaddingimp nomargintop nomarginbottom" name="cancel" value="'.dolPrintHTMLForAttribute($langs->trans('Cancel')).'">';
	$out .= '</form>';

	return $out;
}
