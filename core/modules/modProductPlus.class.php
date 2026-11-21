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
 * \defgroup   productplus     Module ProductPlus
 * \brief      Quick product card for the administrators: search by ref, label or barcode and modify
 *             the main data of the product (ref, label, barcode, description, categories, prices, stock).
 * \file       productplus/core/modules/modProductPlus.class.php
 * \ingroup    productplus
 * \brief      Description and activation file for module ProductPlus
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 * Description and activation class for module ProductPlus
 */
class modProductPlus extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		$this->numero = 958800;
		$this->rights_class = 'productplus';
		$this->family = "products";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "ProductPlusDescription";
		$this->descriptionlong = "ProductPlusDescriptionLong";
		$this->editor_name = 'Edgar Bustamante';
		$this->editor_url = '';
		$this->version = '1.2.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-edit';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
			'captcha' => 0,
		);

		$this->dirs = array();
		$this->config_page_url = array("about.php@productplus");

		$this->hidden = getDolGlobalInt('MODULE_PRODUCTPLUS_DISABLED');
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array("productplus@productplus");

		$this->phpmin = array(7, 2);
		$this->need_dolibarr_version = array(19, -3);
		$this->need_javascript_ajax = 0;

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		$this->const = array();

		if (!isModEnabled("productplus")) {
			$conf->productplus = new stdClass();
			$conf->productplus->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		// Permission to use the card. What can be changed on it still depends on the native rights (products, stock...)
		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero * 100 + 1;
		$this->rights[$r][1] = 'ProductPlusPermUse';
		$this->rights[$r][4] = 'use';
		$this->rights[$r][5] = '';
		$r++;

		// Menu: Products > Product card
		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=products',
			'type' => 'left',
			'titre' => 'ProductPlusMenuCard',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth"'),
			'mainmenu' => 'products',
			'leftmenu' => 'productplus',
			'url' => '/productplus/ficha_producto.php',
			'langs' => 'productplus@productplus',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("productplus")',
			'perms' => '$user->hasRight("productplus", "use")',
			'target' => '',
			'user' => 0,
		);
	}

	/**
	 * Function called when module is enabled.
	 * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *
	 * @param	string		$options	Options when enabling module ('', 'noboxes')
	 * @return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/productplus/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 *
	 * @param	string		$options	Options when enabling module ('', 'noboxes')
	 * @return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
