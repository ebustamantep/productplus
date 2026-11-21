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
 * \file    productplus/ficha_producto.php
 * \ingroup productplus
 * \brief   Quick product card: search by ref, label or barcode, then modify ref, label, barcode,
 *          description, categories, selling price, purchase price and stock by warehouse. Admins only.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 * @var Societe $mysoc
 */

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/genericobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formbarcode.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
dol_include_once('/productplus/lib/productplus.lib.php');

$langs->loadLangs(array('products', 'stocks', 'suppliers', 'categories', 'bills', 'errors', 'productplus@productplus'));

// Permission of the module, plus the native right to read products or services
$readabletypes = array();
if (isModEnabled('product') && $user->hasRight('produit', 'lire')) {
	$readabletypes[] = Product::TYPE_PRODUCT;
}
if (isModEnabled('service') && $user->hasRight('service', 'lire')) {
	$readabletypes[] = Product::TYPE_SERVICE;
}
if (!$user->hasRight('productplus', 'use') || !count($readabletypes)) {
	accessforbidden();
}
if (!isModEnabled('productplus') || (!isModEnabled('product') && !isModEnabled('service'))) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');
$search = trim(GETPOST('search', 'alphanohtml'));
$level = GETPOSTINT('level');
$pfpid = GETPOSTINT('pfpid');
$whid = GETPOSTINT('whid');

$usebarcode = isModEnabled('barcode');
$usecategories = isModEnabled('category');
$usesuppliers = (isModEnabled('supplier_order') || isModEnabled('supplier_invoice') || isModEnabled('supplier_proposal'));
$usestock = isModEnabled('stock');
$usemulticurrency = isModEnabled('multicurrency');
$multiprices = (getDolGlobalString('PRODUIT_MULTIPRICES') || getDolGlobalString('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES'));
$nblevels = $multiprices ? max(1, getDolGlobalInt('PRODUIT_MULTIPRICES_LIMIT', 5)) : 0;

$object = new Product($db);
if ($id > 0) {
	if ($object->fetch($id) <= 0) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
	// Entity check of the native product card
	restrictedArea($user, 'produit|service', $object->id, 'product&product', '', '', 'rowid');
	if (!in_array((int) $object->type, $readabletypes)) {
		accessforbidden();
	}
}

// Same rights as the native pages of the product (card, prices, supplier prices, stock)
$isservice = ((int) $object->type == Product::TYPE_SERVICE);
$advperms = getDolGlobalString('MAIN_USE_ADVANCED_PERMS');
$usercancreate = ($isservice ? $user->hasRight('service', 'creer') : $user->hasRight('produit', 'creer'));
$usercanreadprice = ($advperms ? $user->hasRight($isservice ? 'service' : 'product', $isservice ? 'service_advance' : 'product_advance', 'read_prices') : true);
$usercanreadsupplierprice = ($advperms ? $user->hasRight($isservice ? 'service' : 'product', $isservice ? 'service_advance' : 'product_advance', 'read_supplier_prices') : true);
$usercanreadsupplierprice = ($usercanreadsupplierprice && ($user->hasRight('fournisseur', 'lire') || (isModEnabled('margin') && $user->hasRight('margin', 'liretous'))));
$usercanwritesupplierprice = ($advperms ? $user->hasRight('product', 'product_advance', 'write_supplier_prices') : $usercancreate);
$usercancreatecategory = $user->hasRight('categorie', 'creer');
$usercanreadstock = $user->hasRight('stock', 'lire');
$usercanupdatestock = $user->hasRight('stock', 'mouvement', 'creer');
$usesuppliers = ($usesuppliers && $usercanreadsupplierprice);
$usestock = ($usestock && $usercanreadstock);
$usecategories = ($usecategories && $user->hasRight('categorie', 'lire'));

$hookmanager->initHooks(array('productplusficha', 'globalcard'));

$selfurl = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);


/*
 * Actions
 */

$parameters = array('id' => $object->id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if ($cancel) {
	$action = '';
}

// Changes only by POST (its CSRF token is always checked by main.inc.php), never by a link, whatever the CSRF setup
if (preg_match('/^(set|add)/', $action) && (empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST')) {
	accessforbidden();
}

$searchresult = null;
if (empty($reshook) && $search !== '' && $action == '') {
	$searchresult = productplus_search_products($db, $search, 100, $readabletypes);
	if (count($searchresult['rows']) == 1 && !$searchresult['more']) {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $searchresult['rows'][0]->rowid));
		exit;
	}
}

// Fields saved with Product::update(): ref, label, barcode, description, cost price
// An action or an edit form without the native right is refused
$needright = array(
	'ref' => $usercancreate, 'label' => $usercancreate, 'barcode' => $usercancreate, 'desc' => $usercancreate,
	'categories' => $usercancreate, 'price' => $usercancreate && $usercanreadprice,
	'costprice' => $usercancreate && $usercanreadsupplierprice,
	'supplierprice' => $usercanwritesupplierprice && $usesuppliers, 'stock' => $usercanupdatestock && $usestock,
);
if ($object->id > 0 && preg_match('/^(set|edit|add|create)(.+)$/', $action, $reg) && isset($needright[$reg[2]]) && !$needright[$reg[2]]) {
	accessforbidden();
}

if (empty($reshook) && $object->id > 0 && in_array($action, array('setref', 'setlabel', 'setbarcode', 'setdesc', 'setcostprice'))) {
	$error = 0;
	$object->oldcopy = dol_clone($object, 1);  // @phan-suppress-current-line PhanTypeMismatchProperty

	if ($action == 'setref') {
		$newref = trim(GETPOST('newref', 'alphanohtml'));
		if ($newref === '') {
			$error++;
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Ref')), null, 'errors');
		}
		$object->ref = $newref;
	} elseif ($action == 'setlabel') {
		$newlabel = trim(GETPOST('label', 'alphanohtml'));
		if ($newlabel === '') {
			$error++;
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Label')), null, 'errors');
		}
		$object->label = $newlabel;
	} elseif ($action == 'setbarcode') {
		$newbarcode = trim(GETPOST('barcode', 'alphanohtml'));
		$fk_barcode_type = GETPOSTINT('fk_barcode_type');
		if ($newbarcode !== '' && empty($fk_barcode_type)) {
			$fk_barcode_type = getDolGlobalInt('PRODUIT_DEFAULT_BARCODE_TYPE');
		}
		$object->barcode = $newbarcode;
		$object->barcode_type = $fk_barcode_type;
		// barcode_type_code is required by the check of the barcode numbering module
		$stdobject = new GenericObject($db);
		$stdobject->element = 'product';
		$stdobject->barcode_type = $fk_barcode_type;
		if ($stdobject->fetchBarCode() < 0) {
			$error++;
			setEventMessages($stdobject->error, $stdobject->errors, 'errors');
		}
		$object->barcode_type_code = $stdobject->barcode_type_code;
		$object->barcode_type_coder = $stdobject->barcode_type_coder;
		$object->barcode_type_label = $stdobject->barcode_type_label;

		// Without a barcode numbering module Dolibarr does not check duplicates: a duplicate breaks the scanning
		if (!$error && $newbarcode !== '' && !getDolGlobalString('BARCODE_PRODUCT_ADDON_NUM')) {
			$sql = "SELECT p.rowid, p.ref FROM ".$db->prefix()."product as p";
			$sql .= " WHERE p.barcode = '".$db->escape($newbarcode)."' AND p.rowid <> ".((int) $object->id);
			$sql .= " AND p.entity IN (".getEntity('product').")";
			$rows = productplus_fetch_rows($db, $sql.$db->plimit(1));
			if (count($rows)) {
				$error++;
				setEventMessages($langs->trans('ProductPlusErrorBarcodeUsed', $newbarcode, $rows[0]->ref), null, 'errors');
			}
		}
	} elseif ($action == 'setdesc') {
		$object->description = GETPOST('desc', 'restricthtml');
	} elseif ($action == 'setcostprice') {
		$newcost = productplus_num(GETPOST('cost_price', 'alpha'));
		if ($newcost === false || ($newcost !== null && $newcost < 0)) {
			$error++;
			setEventMessages($langs->trans('ErrorFieldMustBeANumeric', $langs->transnoentitiesnoconv('ProductPlusCostPrice')), null, 'errors');
		}
		$object->cost_price = ($newcost === null || $newcost === false ? '' : $newcost);
	}

	if (!$error) {
		$result = $object->update($object->id, $user);
		if ($result < 0) {
			$error++;
			if ($object->error == 'ErrorProductAlreadyExists') {
				setEventMessages($langs->trans('ErrorProductAlreadyExists', $object->ref), null, 'errors');
			} else {
				setEventMessages('', productplus_errors($object), 'errors');
			}
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$selfurl);
		exit;
	}
	// Show the form again with the posted value; the object goes back to the saved values
	$object->fetch($object->id);
	$action = 'edit'.substr($action, 3);
}

// Categories
if (empty($reshook) && $object->id > 0 && $action == 'setcategories' && $usecategories) {
	$categories = GETPOST('categories', 'array');
	$categories = array_values(array_filter(array_map('intval', is_array($categories) ? $categories : array())));
	$newcatlabel = trim(GETPOST('newcat_label', 'alphanohtml'));
	$newcatparent = max(0, GETPOSTINT('newcat_parent'));
	$error = 0;
	$created = '';

	$db->begin();

	// New category typed on the form: created, then assigned with the selected ones
	if ($newcatlabel !== '' && !$usercancreatecategory) {
		$error++;
		setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
	} elseif ($newcatlabel !== '') {
		$newcat = new Categorie($db);
		$newcat->label = $newcatlabel;
		$newcat->type = Categorie::TYPE_PRODUCT;
		$newcat->fk_parent = $newcatparent;
		$newcat->visible = 0;
		$result = $newcat->create($user);
		if ($result == -4) {
			// Same label under the same parent: the existing one is used
			$sql = "SELECT c.rowid FROM ".$db->prefix()."categorie as c";
			$sql .= " WHERE c.entity IN (".getEntity('category').") AND c.type = ".((int) $newcat->MAP_ID[Categorie::TYPE_PRODUCT]);
			$sql .= " AND c.fk_parent = ".((int) $newcatparent)." AND c.label = '".$db->escape($newcatlabel)."'";
			$rows = productplus_fetch_rows($db, $sql.$db->plimit(1));
			if (count($rows)) {
				$categories[] = (int) $rows[0]->rowid;
			} else {
				$error++;
				setEventMessages('', productplus_errors($newcat), 'errors');
			}
		} elseif ($result < 0) {
			$error++;
			setEventMessages('', productplus_errors($newcat), 'errors');
		} else {
			$categories[] = (int) $result;
			$created = $newcatlabel;
		}
	}

	if (!$error && $object->setCategories(array_values(array_unique($categories))) < 0) {
		$error++;
		setEventMessages('', productplus_errors($object), 'errors');
	}

	if ($error) {
		$db->rollback();
		$action = 'editcategories';
	} else {
		$db->commit();
		if ($created !== '') {
			setEventMessages($langs->trans('ProductPlusCategoryCreated', $created), null, 'mesgs');
		}
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$selfurl);
		exit;
	}
}

// Selling price (standard price or one level of the multiprices)
if (empty($reshook) && $object->id > 0 && $action == 'setprice') {
	$error = 0;
	if (!$multiprices) {
		$level = 0;
	} elseif ($level < 1 || $level > $nblevels) {
		$error++;
		setEventMessages($langs->trans('ErrorBadParameters'), null, 'errors');
	}

	$newprice = productplus_num(GETPOST('price', 'alpha'));
	$newpricebase = (GETPOST('price_base_type', 'aZ09') == 'TTC' ? 'TTC' : 'HT');
	$vat = productplus_parse_vat(GETPOST('tva_tx', 'alpha'), $mysoc);

	if ($newprice === null || $newprice === false || $newprice < 0) {
		$error++;
		setEventMessages($langs->trans('ErrorFieldMustBeANumeric', $langs->transnoentitiesnoconv('SellingPrice')), null, 'errors');
	}

	if (!$error) {
		// The min price stays the same, in the same base as the new price
		if ($level > 0) {
			$newminprice = ($newpricebase == 'TTC' ? ($object->multiprices_min_ttc[$level] ?? 0) : ($object->multiprices_min[$level] ?? 0));
		} else {
			$newminprice = ($newpricebase == 'TTC' ? $object->price_min_ttc : $object->price_min);
		}
		if ($newminprice > 0 && $newprice < $newminprice) {
			$error++;
			setEventMessages($langs->trans('ErrorPriceCantBeLowerThanMinPrice'), null, 'errors');
		}
	}

	if (!$error) {
		$result = $object->updatePrice((float) $newprice, $newpricebase, $user, $vat['rate'], (float) $newminprice, $level, $vat['npr'], 0, 0, $vat['localtaxes'], $vat['code']);
		if ($result < 0) {
			$error++;
			setEventMessages('', productplus_errors($object), 'errors');
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$selfurl);
		exit;
	}
	$action = 'editprice';
}

// Purchase price of a supplier: only the unit price changes, the rest of the supplier price stays
if (empty($reshook) && $object->id > 0 && $action == 'setsupplierprice' && $usesuppliers) {
	$error = 0;
	$pf = new ProductFournisseur($db);
	if ($pfpid <= 0 || $pf->fetch_product_fournisseur_price($pfpid, 1) <= 0 || (int) $pf->fk_product != (int) $object->id) {
		$error++;
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	}

	$unitprice = productplus_num(GETPOST('unitprice', 'alpha'));
	if (!$error && ($unitprice === null || $unitprice === false || $unitprice < 0)) {
		$error++;
		setEventMessages($langs->trans('ErrorFieldMustBeANumeric', $langs->transnoentitiesnoconv('UnitPrice')), null, 'errors');
	}

	if (!$error) {
		$supplier = new Fournisseur($db);
		$supplier->fetch($pf->fourn_id);
		$qty = ((float) $pf->fourn_qty > 0 ? (float) $pf->fourn_qty : 1);
		$pf->product_fourn_packaging = $pf->packaging;

		// With multicurrency the price is typed in the currency of the supplier price
		$mcode = $conf->currency;
		$mtx = 1;
		$mprice = 0;
		$buyprice = (float) $unitprice * $qty;
		if ($usemulticurrency) {
			$mcode = ($pf->fourn_multicurrency_code ? $pf->fourn_multicurrency_code : $conf->currency);
			$mtx = ((float) $pf->fourn_multicurrency_tx > 0 ? (float) $pf->fourn_multicurrency_tx : 1);
			if ($mcode != $conf->currency) {
				$mprice = (float) $unitprice * $qty;
			} else {
				$mtx = 1;
				$mprice = $buyprice;
			}
		}

		$db->begin();
		$result = $pf->update_buyprice($qty, $buyprice, $user, 'HT', $supplier, (int) $pf->fk_availability, $pf->ref_supplier, (float) $pf->fourn_tva_tx, $pf->fourn_charges, (float) $pf->fourn_remise_percent, (float) $pf->fourn_remise, (int) $pf->fourn_tva_npr, $pf->delivery_time_days, (string) $pf->supplier_reputation, array(), (string) $pf->default_vat_code, $mprice, 'HT', $mtx, $mcode, (string) $pf->desc_supplier, (string) $pf->supplier_barcode, (int) $pf->supplier_fk_barcode_type);
		if ($result < 0) {
			$error++;
			$db->rollback();
			setEventMessages('', productplus_errors($pf), 'errors');
		} else {
			$db->commit();
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$selfurl);
		exit;
	}
	$action = 'editsupplierprice';
}

// New purchase price of a supplier (quantity 1, in the main currency)
if (empty($reshook) && $object->id > 0 && $action == 'addsupplierprice' && $usesuppliers) {
	$error = 0;
	$socid = GETPOSTINT('socid');
	$reffourn = trim(GETPOST('ref_fourn', 'alphanohtml'));
	$unitprice = productplus_num(GETPOST('unitprice', 'alpha'));

	if ($socid <= 0) {
		$error++;
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Supplier')), null, 'errors');
	}
	if ($reffourn === '') {
		$error++;
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('RefSupplier')), null, 'errors');
	}
	if ($unitprice === null || $unitprice === false || $unitprice < 0) {
		$error++;
		setEventMessages($langs->trans('ErrorFieldMustBeANumeric', $langs->transnoentitiesnoconv('UnitPrice')), null, 'errors');
	}

	$supplier = new Fournisseur($db);
	if (!$error && $supplier->fetch($socid) <= 0) {
		$error++;
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	}

	if (!$error) {
		$pf = new ProductFournisseur($db);
		$pf->fetch($object->id);

		$db->begin();
		$result = $pf->add_fournisseur($user, $supplier->id, $reffourn, 1);
		if ($result == -3) {
			$error++;
			$tmpproduct = new Product($db);
			$tmpproduct->fetch($pf->product_id_already_linked);
			setEventMessages(str_replace('{s1}', $tmpproduct->getNomUrl(1, 'supplier'), $langs->trans('ReferenceSupplierIsAlreadyAssociatedWithAProduct', '{s1}')), null, 'errors');
		} elseif ($result < 0) {
			$error++;
			setEventMessages('', productplus_errors($pf), 'errors');
		}

		if (!$error) {
			$vattx = get_default_tva($supplier, $mysoc, $object->id);
			$result = $pf->update_buyprice(1, (float) $unitprice, $user, 'HT', $supplier, 0, $reffourn, (float) $vattx, 0, 0, 0, 0, 0, '', array(), '', (float) $unitprice, 'HT', 1, $conf->currency);
			if ($result < 0) {
				$error++;
				setEventMessages('', productplus_errors($pf), 'errors');
			}
		}

		if ($error) {
			$db->rollback();
		} else {
			$db->commit();
		}
	}

	if (!$error) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$selfurl);
		exit;
	}
	$action = 'createsupplierprice';
}

// Stock of a warehouse: the new quantity is typed, the difference is a stock correction movement
if (empty($reshook) && $object->id > 0 && $action == 'setstock' && $usestock) {
	$error = 0;
	$warehouse = new Entrepot($db);
	if ($whid <= 0 || $warehouse->fetch($whid) <= 0) {
		$error++;
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	}
	if (!$error && $object->hasbatch()) {
		$error++;
		setEventMessages($langs->trans('ProductPlusStockBatch'), null, 'errors');
	}

	$newqty = productplus_num(GETPOST('stockqty', 'alpha'), 'MS');
	if (!$error && ($newqty === null || $newqty === false)) {
		$error++;
		setEventMessages($langs->trans('ErrorFieldMustBeANumeric', $langs->transnoentitiesnoconv('ProductPlusNewStock')), null, 'errors');
	}

	if (!$error) {
		$object->load_stock('novirtual');
		$oldqty = (isset($object->stock_warehouse[$whid]) ? (float) $object->stock_warehouse[$whid]->real : 0.0);
		$delta = (float) price2num((float) $newqty - $oldqty, 'MS');

		if ($delta == 0) {
			setEventMessages($langs->trans('ProductPlusStockUnchanged'), null, 'warnings');
		} else {
			$label = trim(GETPOST('stocklabel', 'alphanohtml'));
			if ($label === '') {
				$label = $langs->transnoentitiesnoconv('ProductPlusStockCorrectionLabel');
			}
			// Price 0: the weighted average price does not change
			$result = $object->correct_stock($user, $whid, abs($delta), ($delta > 0 ? 0 : 1), $label, 0, dol_print_date(dol_now(), 'dayhourlog'));
			if ($result < 0) {
				$error++;
				setEventMessages('', productplus_errors($object), 'errors');
			} else {
				setEventMessages($langs->trans('ProductPlusStockCorrected', $warehouse->ref, productplus_qty($oldqty), productplus_qty($newqty)), null, 'mesgs');
			}
		}
	}

	if (!$error) {
		header('Location: '.$selfurl);
		exit;
	}
	$action = 'editstock';
}


/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('ProductPlusCardTitle');
if ($object->id > 0) {
	$title = $object->ref.' - '.$title;
}
llxHeader('', $title, '', '', 0, 0, '', array('/productplus/css/productplus.css'), '', 'mod-productplus page-ficha_producto');

// Search on the right of the title: always there to jump to the next product (a scanner types the barcode and Enter)
$editing = (strpos($action, 'edit') === 0 || $action == 'createsupplierprice');
$searchform = '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="pp-search">';
$searchform .= '<span class="pp-search-field">';
$searchform .= '<span class="fa fa-search pp-search-icon"></span>';
$searchform .= '<input type="search" name="search" class="pp-search-input" value="'.dol_escape_htmltag($searchresult !== null ? $search : '').'"';
$searchform .= ' placeholder="'.dol_escape_htmltag($langs->trans('ProductPlusSearchPlaceholder')).'" title="'.dol_escape_htmltag($langs->trans('ProductPlusSearchHelp')).'"';
$searchform .= ' autocomplete="off" spellcheck="false"'.($editing ? '' : ' autofocus').'>';
$searchform .= '</span>';
$searchform .= '<input type="submit" class="button smallpaddingimp nomargintop nomarginbottom hideonsmartphoneimp" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
$searchform .= '</form>';
print load_fiche_titre($langs->trans('ProductPlusCardTitle'), $searchform, 'product');

// Nothing loaded yet: invite to search
if ($searchresult === null && empty($object->id)) {
	print '<div class="pp-empty">';
	print '<div class="pp-empty-icon"><span class="fa fa-barcode"></span></div>';
	print '<div class="pp-empty-title">'.$langs->trans('ProductPlusEmptyTitle').'</div>';
	print '<div class="opacitymedium">'.$langs->trans('ProductPlusSearchHelp').'</div>';
	print '</div>';
}

// List of matches when the search is not unique
if ($searchresult !== null) {
	$rows = $searchresult['rows'];
	if (!count($rows)) {
		print '<div class="pp-empty">';
		print '<div class="pp-empty-icon"><span class="fa fa-search"></span></div>';
		print '<div class="pp-empty-title">'.$langs->trans('ProductPlusNoResult', dolPrintHTML($search)).'</div>';
		print '<div class="opacitymedium">'.$langs->trans('ProductPlusSearchHelp').'</div>';
		print '</div>';
	} else {
		print productplus_section($langs->trans('ProductPlusResults', count($rows), dolPrintHTML($search)), 'fa-list');
		if ($searchresult['more']) {
			print '<div class="warning">'.$langs->trans('ProductPlusTooManyResults', count($rows)).'</div>';
		}
		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans('Ref').'</th>';
		print '<th>'.$langs->trans('Label').'</th>';
		if ($usebarcode) {
			print '<th>'.$langs->trans('ProductPlusBarcode').'</th>';
		}
		$showpricecol = (!$advperms || $user->hasRight('product', 'product_advance', 'read_prices') || $user->hasRight('service', 'service_advance', 'read_prices'));
		if ($showpricecol) {
			print '<th class="right">'.$langs->trans('SellingPrice').'</th>';
		}
		if ($usestock) {
			print '<th class="center">'.$langs->trans('PhysicalStock').'</th>';
		}
		print '</tr>';
		foreach ($rows as $row) {
			$url = $_SERVER['PHP_SELF'].'?id='.((int) $row->rowid);
			print '<tr class="oddeven">';
			print '<td class="nowraponall"><a href="'.dol_escape_htmltag($url).'">'.img_picto('', ($row->fk_product_type == Product::TYPE_SERVICE ? 'service' : 'product'), 'class="pictofixedwidth"').dolPrintHTML($row->ref).'</a></td>';
			print '<td class="tdoverflowmax300" title="'.dol_escape_htmltag($row->label).'"><a href="'.dol_escape_htmltag($url).'">'.dolPrintHTML($row->label).'</a></td>';
			if ($usebarcode) {
				print '<td class="nowraponall"><span class="opacitymedium">'.dolPrintHTML((string) $row->barcode).'</span></td>';
			}
			if ($showpricecol) {
				$showprice = ($row->price_base_type == 'TTC' ? $row->price_ttc : $row->price);
				print '<td class="right nowraponall"><span class="amount">'.price($showprice, 0, $langs, 0, -1, -1, $conf->currency).'</span> <span class="opacitymedium small">'.$langs->trans($row->price_base_type == 'TTC' ? 'TTC' : 'HT').'</span></td>';
			}
			if ($usestock) {
				print '<td class="center">'.($row->fk_product_type == Product::TYPE_SERVICE ? '' : productplus_qty_badge($row->stock)).'</td>';
			}
			print '</tr>';
		}
		print '</table>';
		print '</div>';
	}
}

if ($object->id > 0) {
	$head = array(array($selfurl, $langs->trans('ProductPlusCardTitle'), 'card'));
	print dol_get_fiche_head($head, 'card', $langs->trans('Product'), -1, $object->picto);

	// Native place of "Back to list": top right, next to the previous/next arrows
	$linkfullcard = '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.((int) $object->id).'" title="'.dol_escape_htmltag($langs->trans('ProductPlusFullCardHelp')).'">';
	$linkfullcard .= img_picto('', 'fa-external-link-alt', 'class="pictofixedwidth"').$langs->trans('ProductPlusFullCard').'</a>';
	dol_banner_tab($object, 'id', $linkfullcard, 1, 'rowid', 'ref');

	print '<div class="fichecenter">';

	/*
	 * Left: general data and stock
	 */
	print '<div class="fichehalfleft">';

	print productplus_section($langs->trans('ProductPlusGeneral'), 'fa-info-circle');
	print '<table class="border tableforfield centpercent">';

	// Ref
	print '<tr'.($action == 'editref' ? ' class="pp-editing"' : '').'><td class="titlefield">'.productplus_field_label($langs->trans('Ref'), 'editref', $action != 'editref' && $needright['ref'], $object->id).'</td><td>';
	if ($action == 'editref') {
		$value = GETPOSTISSET('newref') ? GETPOST('newref', 'alphanohtml') : $object->ref;
		print productplus_form_start($object->id, 'setref');
		print '<input type="text" name="newref" class="minwidth150 maxwidth200" maxlength="128" value="'.dol_escape_htmltag($value).'" autofocus spellcheck="false">';
		print productplus_form_end();
	} else {
		print '<strong>'.dolPrintHTML($object->ref).'</strong>';
	}
	print '</td></tr>';

	// Label
	print '<tr'.($action == 'editlabel' ? ' class="pp-editing"' : '').'><td>'.productplus_field_label($langs->trans('Label'), 'editlabel', $action != 'editlabel' && $needright['label'], $object->id).'</td><td>';
	if ($action == 'editlabel') {
		$value = GETPOSTISSET('label') ? GETPOST('label', 'alphanohtml') : $object->label;
		print productplus_form_start($object->id, 'setlabel');
		print '<input type="text" name="label" class="minwidth200 widthcentpercentminusx" maxlength="255" value="'.dol_escape_htmltag($value).'" autofocus>';
		print '<span class="pp-form-break"></span>';
		print productplus_form_end();
	} else {
		print dolPrintHTML($object->label);
	}
	print '</td></tr>';

	// Barcode (value and type)
	if ($usebarcode) {
		print '<tr'.($action == 'editbarcode' ? ' class="pp-editing"' : '').'><td>'.productplus_field_label($langs->trans('ProductPlusBarcode'), 'editbarcode', $action != 'editbarcode' && $needright['barcode'], $object->id).'</td><td>';
		if ($action == 'editbarcode') {
			$formbarcode = new FormBarCode($db);
			$value = GETPOSTISSET('barcode') ? GETPOST('barcode', 'alphanohtml') : $object->barcode;
			$fk_barcode_type = GETPOSTISSET('fk_barcode_type') ? GETPOSTINT('fk_barcode_type') : (int) $object->barcode_type;
			if (empty($fk_barcode_type)) {
				$fk_barcode_type = getDolGlobalInt('PRODUIT_DEFAULT_BARCODE_TYPE');
			}
			print productplus_form_start($object->id, 'setbarcode');
			print img_picto('', 'barcode', 'class="pictofixedwidth"').'<input type="text" name="barcode" class="minwidth150 maxwidth200" maxlength="180" value="'.dol_escape_htmltag((string) $value).'" autofocus spellcheck="false">';
			print $formbarcode->selectBarcodeType($fk_barcode_type, 'fk_barcode_type', 1);
			print '<span class="pp-form-break"></span>';
			print productplus_form_end();
		} elseif ($object->barcode) {
			print img_picto('', 'barcode', 'class="pictofixedwidth opacitymedium"').dolPrintHTML((string) $object->barcode);
			$object->fetchBarCode();
			if ($object->barcode_type_label) {
				print ' <span class="badge badge-secondary">'.dolPrintHTML($object->barcode_type_label).'</span>';
			}
		} else {
			print '<span class="pp-muted">'.$langs->trans('ProductPlusNotSet').'</span>';
		}
		print '</td></tr>';
	}

	// Description
	print '<tr'.($action == 'editdesc' ? ' class="pp-editing"' : '').'><td class="tdtop">'.productplus_field_label($langs->trans('Description'), 'editdesc', $action != 'editdesc' && $needright['desc'], $object->id).'</td><td>';
	if ($action == 'editdesc') {
		$value = GETPOSTISSET('desc') ? GETPOST('desc', 'restricthtml') : $object->description;
		print productplus_form_start($object->id, 'setdesc');
		print '<div class="centpercent">';
		$doleditor = new DolEditor('desc', $value, '', 160, 'dolibarr_details', '', false, true, getDolGlobalInt('FCKEDITOR_ENABLE_PRODUCTDESC'), ROWS_4, '100%');
		$doleditor->Create();
		print '</div>';
		print productplus_form_end();
	} elseif (trim((string) $object->description) !== '') {
		print '<div class="longmessagecut">'.dolPrintHTML($object->description).'</div>';
	} else {
		print '<span class="pp-muted">'.$langs->trans('ProductPlusNotSet').'</span>';
	}
	print '</td></tr>';

	// Categories
	if ($usecategories) {
		print '<tr'.($action == 'editcategories' ? ' class="pp-editing"' : '').'><td class="tdtop">'.productplus_field_label($langs->trans('Categories'), 'editcategories', $action != 'editcategories' && $needright['categories'], $object->id).'</td><td>';
		if ($action == 'editcategories') {
			$cate_arbo = $form->select_all_categories(Categorie::TYPE_PRODUCT, '', 'parent', 64, 0, 3);
			$arrayselected = array();
			if (GETPOSTISSET('categories')) {
				$arrayselected = array_map('intval', (array) GETPOST('categories', 'array'));
			} else {
				$c = new Categorie($db);
				$cats = $c->containing($object->id, Categorie::TYPE_PRODUCT);
				if (is_array($cats)) {
					foreach ($cats as $cat) {
						$arrayselected[] = $cat->id;
					}
				}
			}
			print productplus_form_start($object->id, 'setcategories');
			print '<div class="centpercent">'.img_picto('', 'category', 'class="pictofixedwidth"').$form->multiselectarray('categories', $cate_arbo, $arrayselected, 0, 0, 'minwidth200 widthcentpercentminusx', 0, 0).'</div>';
			// Create a category without leaving the card
			if ($usercancreatecategory) {
				print '<div class="pp-newcat centpercent">';
				print '<div class="pp-newcat-title">'.img_picto('', 'fa-plus-circle', 'class="pictofixedwidth"').$langs->trans('ProductPlusNewCategory').'</div>';
				print '<input type="text" name="newcat_label" class="minwidth150 maxwidth200" maxlength="180" value="'.dol_escape_htmltag(GETPOST('newcat_label', 'alphanohtml')).'" placeholder="'.dol_escape_htmltag($langs->trans('ProductPlusNewCategoryLabel')).'">';
				print '<span class="nowraponall">'.$langs->trans('ProductPlusNewCategoryParent').' ';
				print $form->select_all_categories(Categorie::TYPE_PRODUCT, (GETPOSTINT('newcat_parent') > 0 ? GETPOSTINT('newcat_parent') : ''), 'newcat_parent', 64, 0, 0, 0, 'maxwidth150', 1).'</span>';
				print '</div>';
			}
			print productplus_form_end();
		} else {
			$cathtml = $form->showCategories($object->id, Categorie::TYPE_PRODUCT, 1);
			print (trim(strip_tags($cathtml)) !== '' ? $cathtml : '<span class="pp-muted">'.$langs->trans('ProductPlusNotSet').'</span>');
		}
		print '</td></tr>';
	}

	print '</table>';

	// Stock by warehouse
	if ($usestock && ($object->isProduct() || getDolGlobalString('STOCK_SUPPORTS_SERVICES'))) {
		$hasbatch = $object->hasbatch();
		$canedit = (!$hasbatch && $needright['stock']);

		// All the open warehouses, and the closed ones that still have stock of the product
		$sql = "SELECT e.rowid, e.ref, e.lieu, e.statut as status, ps.reel";
		$sql .= " FROM ".$db->prefix()."entrepot as e";
		$sql .= " LEFT JOIN ".$db->prefix()."product_stock as ps ON ps.fk_entrepot = e.rowid AND ps.fk_product = ".((int) $object->id);
		$sql .= " WHERE e.entity IN (".getEntity('stock').")";
		$sql .= " AND (e.statut <> ".((int) Entrepot::STATUS_CLOSED)." OR ps.reel <> 0)";
		$sql .= $db->order('e.ref', 'ASC');
		$warehouses = productplus_fetch_rows($db, $sql);

		$linkstock = '<a class="smallpaddingimp" href="'.DOL_URL_ROOT.'/product/stock/product.php?id='.((int) $object->id).'" title="'.dol_escape_htmltag($langs->trans('ProductPlusGoToStock')).'">'.img_picto($langs->trans('ProductPlusGoToStock'), 'fa-external-link-alt').'</a>';
		print productplus_section($form->textwithpicto($langs->trans('ProductPlusStockByWarehouse'), $langs->trans('ProductPlusStockHelp')), 'stock', $linkstock);

		if ($hasbatch && $needright['stock']) {
			print '<div class="info">'.$langs->trans('ProductPlusStockBatch').' <a href="'.DOL_URL_ROOT.'/product/stock/product.php?id='.((int) $object->id).'">'.$langs->trans('ProductPlusGoToStock').'</a></div>';
		}

		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans('Warehouse').'</th>';
		print '<th class="center width100">'.$langs->trans('PhysicalStock').'</th>';
		print '<th class="right width25"></th>';
		print '</tr>';

		if (!count($warehouses)) {
			print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('ProductPlusNoWarehouse').'</span></td></tr>';
		}

		$total = 0;
		foreach ($warehouses as $wh) {
			$qty = (float) $wh->reel;
			$total += $qty;
			$whstatic = new Entrepot($db);
			$whstatic->id = $wh->rowid;
			$whstatic->ref = $wh->ref;
			$whstatic->label = $wh->ref;
			$whstatic->lieu = $wh->lieu;
			$whstatic->statut = $wh->status;
			$whstatic->status = $wh->status;
			$editthis = ($action == 'editstock' && $whid == (int) $wh->rowid && $canedit);

			print '<tr class="oddeven">';
			print '<td class="tdoverflowmax250">'.$whstatic->getNomUrl(1);
			if ($wh->lieu) {
				print '<br><span class="opacitymedium small">'.dolPrintHTML($wh->lieu).'</span>';
			}
			print '</td>';
			print '<td class="center">'.productplus_qty_badge($qty).'</td>';
			print '<td class="right nowraponall">';
			if ($canedit && !$editthis) {
				$url = $selfurl.'&action=editstock&whid='.((int) $wh->rowid).'&token='.newToken();
				print '<a class="editfielda" href="'.dol_escape_htmltag($url).'">'.img_edit($langs->trans('Modify'), 1).'</a>';
			}
			print '</td>';
			print '</tr>';

			// Edit form on its own full row under the warehouse
			if ($editthis) {
				$value = GETPOSTISSET('stockqty') ? GETPOST('stockqty', 'alpha') : productplus_qty($qty);
				print '<tr class="pp-editing"><td colspan="3">';
				print productplus_form_start($object->id, 'setstock', array('whid' => (int) $wh->rowid));
				print '<label for="pp-stockqty">'.$langs->trans('ProductPlusNewStock').'</label>';
				print '<input type="text" id="pp-stockqty" name="stockqty" class="width75 right" value="'.dol_escape_htmltag((string) $value).'" autofocus>';
				print '<input type="text" name="stocklabel" class="minwidth200 maxwidth300" maxlength="255" value="'.dol_escape_htmltag(GETPOST('stocklabel', 'alphanohtml')).'" placeholder="'.dol_escape_htmltag($langs->trans('ProductPlusStockCorrectionLabel')).'" title="'.dol_escape_htmltag($langs->trans('ProductPlusStockReason')).'">';
				print productplus_form_end();
				print '</td></tr>';
			}
		}

		if (count($warehouses)) {
			print '<tr class="liste_total"><td>'.$langs->trans('Total').'</td><td class="center"><strong>'.productplus_qty($total).'</strong></td><td></td></tr>';
		}
		print '</table>';
		print '</div>';
	}

	print '</div>';

	/*
	 * Right: selling price, purchase price, supplier prices
	 */
	print '<div class="fichehalfright">';

	$levels = ($usercanreadprice ? ($multiprices ? range(1, $nblevels) : array(0)) : array());
	if (count($levels)) {
		print productplus_section($langs->trans('SellingPrice'), 'fa-tag');
		print '<table class="border tableforfield centpercent">';
	}
	foreach ($levels as $lvl) {
		if ($lvl > 0) {
			$price = $object->multiprices[$lvl] ?? 0;
			$pricettc = $object->multiprices_ttc[$lvl] ?? 0;
			$pricebase = $object->multiprices_base_type[$lvl] ?? 'HT';
			$vattx = $object->multiprices_tva_tx[$lvl] ?? $object->tva_tx;
			$label = $langs->trans('ProductPlusSellingPriceLevel', $lvl);
			if (getDolGlobalString('PRODUIT_MULTIPRICES_LABEL'.$lvl)) {
				$label .= '<br><span class="opacitymedium small">'.dolPrintHTML($langs->trans(getDolGlobalString('PRODUIT_MULTIPRICES_LABEL'.$lvl))).'</span>';
			}
		} else {
			$price = $object->price;
			$pricettc = $object->price_ttc;
			$pricebase = $object->price_base_type;
			$vattx = $object->tva_tx;
			$label = $langs->trans('ProductPlusPrice');
		}
		$pricebase = ($pricebase == 'TTC' ? 'TTC' : 'HT');
		$editthis = ($action == 'editprice' && $level == $lvl);

		print '<tr'.($editthis ? ' class="pp-editing"' : '').'><td class="titlefield">'.productplus_field_label($label, 'editprice', !$editthis && $needright['price'], $object->id, '&level='.$lvl).'</td><td>';
		if ($editthis) {
			$value = GETPOSTISSET('price') ? GETPOST('price', 'alpha') : price($pricebase == 'TTC' ? $pricettc : $price);
			$base = GETPOSTISSET('price_base_type') ? GETPOST('price_base_type', 'aZ09') : $pricebase;
			$vatselected = GETPOSTISSET('tva_tx') ? GETPOST('tva_tx', 'alpha') : ($lvl == 0 && $object->default_vat_code ? $object->tva_tx.' ('.$object->default_vat_code.')' : $vattx);
			print productplus_form_start($object->id, 'setprice', array('level' => $lvl));
			print '<input type="text" name="price" class="width100 right" value="'.dol_escape_htmltag((string) $value).'" autofocus>';
			print $form->selectPriceBaseType($base, 'price_base_type');
			print '<span class="nowraponall">'.$langs->trans('VAT').' '.$form->load_tva('tva_tx', $vatselected, $mysoc, null, $object->id, $object->tva_npr, $object->type, false, 1).'</span>';
			print '<span class="pp-form-break"></span>';
			print productplus_form_end();
		} else {
			print '<div><span class="pp-price-amount amount">'.price($pricebase == 'TTC' ? $pricettc : $price, 0, $langs, 0, -1, -1, $conf->currency).'</span>';
			print '<span class="pp-price-base">'.$langs->trans($pricebase).'</span></div>';
			print '<div class="pp-price-detail">'.price($pricebase == 'TTC' ? $price : $pricettc, 0, $langs, 0, -1, -1, $conf->currency).' '.$langs->trans($pricebase == 'TTC' ? 'HT' : 'TTC');
			print ' &middot; '.$langs->trans('VAT').' '.vatrate((string) $vattx, true).'</div>';
		}
		print '</td></tr>';
	}
	if (count($levels)) {
		print '</table>';
	}

	// Purchase price
	if ($usercanreadsupplierprice) {
		print productplus_section($langs->trans('ProductPlusPurchasePrice'), 'fa-shopping-cart');
		print '<table class="border tableforfield centpercent">';
		print '<tr'.($action == 'editcostprice' ? ' class="pp-editing"' : '').'><td class="titlefield">'.productplus_field_label($langs->trans('ProductPlusCostPrice'), 'editcostprice', $action != 'editcostprice' && $needright['costprice'], $object->id).'</td><td>';
		if ($action == 'editcostprice') {
			$value = GETPOSTISSET('cost_price') ? GETPOST('cost_price', 'alpha') : ($object->cost_price !== null && $object->cost_price !== '' ? price($object->cost_price) : '');
			print productplus_form_start($object->id, 'setcostprice');
			print '<input type="text" name="cost_price" class="width100 right" value="'.dol_escape_htmltag((string) $value).'" autofocus><span>'.$langs->trans('HT').'</span>';
			print productplus_form_end();
		} elseif ($object->cost_price !== null && $object->cost_price !== '') {
			print '<span class="amount">'.price($object->cost_price, 0, $langs, 0, -1, -1, $conf->currency).'</span> <span class="opacitymedium">'.$langs->trans('HT').'</span>';
		} else {
			print '<span class="pp-muted">'.$langs->trans('ProductPlusNotSet').'</span>';
	}
	print '</td></tr>';
	print '<tr><td>'.$form->textwithpicto($langs->trans('ProductPlusPmp'), $langs->trans('PMPValue').': '.$langs->trans('ProductPlusPmpHelp')).'</td><td>';
	print '<span class="amount">'.price($object->pmp, 0, $langs, 0, -1, -1, $conf->currency).'</span> <span class="opacitymedium">'.$langs->trans('HT').'</span>';
	print '</td></tr>';
	print '</table>';
	}

	// Supplier prices
	if ($usesuppliers) {
		$pfstatic = new ProductFournisseur($db);
		$supplierprices = $pfstatic->list_product_fournisseur_price($object->id, 's.nom', 'ASC');
		$supplierprices = is_array($supplierprices) ? $supplierprices : array();

		$addbutton = (!$needright['supplierprice'] ? '' : dolGetButtonTitle($langs->trans('ProductPlusAddSupplierPrice'), '', 'fa fa-plus-circle', $selfurl.'&action=createsupplierprice&token='.newToken(), '', ($action == 'createsupplierprice' ? -1 : 1)));
		print productplus_section($langs->trans('ProductPlusSupplierPrices'), 'supplier', $addbutton);

		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th>'.$langs->trans('Supplier').'</th>';
		print '<th class="nowraponall" title="'.dol_escape_htmltag($langs->trans('RefSupplier')).'">'.$langs->trans('Ref').'</th>';
		print '<th class="right nowraponall" title="'.dol_escape_htmltag($langs->trans('QtyMin')).'">'.$langs->trans('ProductPlusQtyMinShort').'</th>';
		print '<th class="right nowraponall" title="'.dol_escape_htmltag($langs->trans('ProductPlusUnitPrice')).'">'.$langs->trans('ProductPlusUnitPriceShort').'</th>';
		print '<th class="right width25"></th>';
		print '</tr>';

		if (!count($supplierprices) && $action != 'createsupplierprice') {
			print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('ProductPlusNoSupplierPrice').'</span></td></tr>';
		}

		foreach ($supplierprices as $sp) {
			$editthis = ($action == 'editsupplierprice' && $pfpid == (int) $sp->product_fourn_price_id);
			$foreign = ($usemulticurrency && !empty($sp->fourn_multicurrency_code) && $sp->fourn_multicurrency_code != $conf->currency);
			$supplierstatic = new Fournisseur($db);
			$supplierstatic->id = $sp->fourn_id;
			$supplierstatic->name = $sp->fourn_name;
			$supplierstatic->fournisseur = 1;

			print '<tr class="oddeven'.($editthis ? ' pp-editing' : '').'">';
			print '<td class="tdoverflowmax200">'.$supplierstatic->getNomUrl(1, 'supplier').'</td>';
			print '<td class="tdoverflowmax100 small" title="'.dol_escape_htmltag($sp->ref_supplier).'">'.dolPrintHTML($sp->ref_supplier).'</td>';
			print '<td class="right">'.productplus_qty($sp->fourn_qty).'</td>';
			if ($editthis) {
				$current = ($foreign ? $sp->fourn_multicurrency_unitprice : $sp->fourn_unitprice);
				$value = GETPOSTISSET('unitprice') ? GETPOST('unitprice', 'alpha') : price($current);
				print '<td class="right" colspan="2">';
				print productplus_form_start($object->id, 'setsupplierprice', array('pfpid' => (int) $sp->product_fourn_price_id));
				print '<input type="text" name="unitprice" class="width75 right" value="'.dol_escape_htmltag((string) $value).'" autofocus>';
				print '<span>'.dolPrintHTML($foreign ? $sp->fourn_multicurrency_code : $conf->currency).'</span>';
				print '<span class="pp-form-break"></span>';
				print productplus_form_end();
				print '</td>';
			} else {
				print '<td class="right nowraponall"><span class="amount">'.price($sp->fourn_unitprice, 0, $langs, 0, -1, -1, $conf->currency).'</span>';
				if ($foreign) {
					print '<br><span class="opacitymedium small">'.price($sp->fourn_multicurrency_unitprice, 0, $langs, 0, -1, -1, $sp->fourn_multicurrency_code).'</span>';
				}
				print '</td>';
				print '<td class="right nowraponall">';
				if ($needright['supplierprice']) {
					$url = $selfurl.'&action=editsupplierprice&pfpid='.((int) $sp->product_fourn_price_id).'&token='.newToken();
					print '<a class="editfielda" href="'.dol_escape_htmltag($url).'">'.img_edit($langs->trans('Modify'), 1).'</a>';
				}
				print '</td>';
			}
			print '</tr>';
		}

		if ($action == 'createsupplierprice') {
			print '<tr class="oddeven pp-editing"><td colspan="5">';
			print productplus_form_start($object->id, 'addsupplierprice');
			print img_picto('', 'company', 'class="pictofixedwidth"').$form->select_company(GETPOSTINT('socid'), 'socid', '(fournisseur:=:1) AND (status:=:1)', $langs->transnoentitiesnoconv('Supplier'), 0, 0, array(), 0, 'minwidth200');
			print '<span class="pp-form-break"></span>';
			print '<input type="text" name="ref_fourn" class="width150" maxlength="128" value="'.dol_escape_htmltag(GETPOST('ref_fourn', 'alphanohtml')).'" placeholder="'.dol_escape_htmltag($langs->trans('RefSupplier')).'">';
			print '<input type="text" name="unitprice" class="width125 right" value="'.dol_escape_htmltag(GETPOST('unitprice', 'alpha')).'" placeholder="'.dol_escape_htmltag($langs->trans('UnitPrice')).'">';
			print '<span>'.dolPrintHTML($conf->currency).' '.$langs->trans('HT').'</span>';
			print '<span class="pp-form-break"></span>';
			print '<span class="opacitymedium small">'.$langs->trans('ProductPlusAddSupplierPriceHelp').'</span>';
			print '<span class="pp-form-break"></span>';
			print productplus_form_end();
			print '</td></tr>';
		}

		print '</table>';
		print '</div>';
	}

	print '</div>';
	print '</div>';
	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();
}

llxFooter();
$db->close();
