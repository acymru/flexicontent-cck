<?php
/**
 * @version 1.5 stable $Id: flexicontent.helper.php 1966 2014-09-21 17:33:27Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

if (!function_exists('json_encode')) { // PHP < 5.2 lack support for json
	require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'json'.DS.'jsonwrapper_inner.php');
} 

class flexicontent_html
{
	static function getDefaultCanonical()
	{
		$app = JFactory::getApplication();
		$doc = JFactory::getDocument();

		if ($app->getName() != 'site' || $doc->getType() !== 'html')
		{
			return;
		}

		$router = $app->getRouter();
		
		$uri = clone JUri::getInstance();
		// Get configuration from plugin
		$plugin = JPluginHelper::getPlugin('system', 'sef');
		$domain = null;
		if (!empty($plugin)) {
			$pluginParams = FLEXI_J16GE ? new JRegistry($plugin->params) : new JParameter($plugin->params);
			$domain = $pluginParams->get('domain');
		}

		if ($domain === null || $domain === '')
		{
			$domain = $uri->toString(array('scheme', 'host', 'port'));
		}

		$parsed = $router->parse($uri);
		$fakelink = 'index.php?' . http_build_query($parsed);
		$link = $domain . JRoute::_($fakelink, false);

		return ($uri !== $link) ? htmlspecialchars($link) : false;
	}


	// *** Output the javascript to dynamically hide/show columns of a table
	static function jscode_to_showhide_table($container_div_id, $data_tbl_id, $start_html='', $end_html='') {
		$document = JFactory::getDocument();
		$js = "
		var show_col_${data_tbl_id} = Array();
		jQuery(document).ready(function() {
	  ";
	  
	  if (isset($_POST["columnchoose_${data_tbl_id}"])) {
	    foreach ($_POST["columnchoose_${data_tbl_id}"] as $colnum => $ignore) {
      	$js .= "show_col_${data_tbl_id}[".$colnum."]=1; \n";
	    }
 	  }
 	  else if (isset($_COOKIE["columnchoose_${data_tbl_id}"])) {
 	  	$colnums = preg_split("/[\s]*,[\s]*/", $_COOKIE["columnchoose_${data_tbl_id}"]);
	  	foreach ($colnums as $colnum) {
	    	$colnum = (int) $colnum;
      	$js .= "show_col_${data_tbl_id}[".$colnum."]=1; \n";
	    }
		}
	  
	  $firstload = isset($_POST["columnchoose_${data_tbl_id}"]) || isset($_COOKIE["columnchoose_${data_tbl_id}"]) ? "false" : "true";
	  $js .= "create_column_choosers('$container_div_id', '$data_tbl_id', $firstload, '".$start_html."', '".$end_html."'); \n";
	  
		$js .= "
		});
		";
		$document->addScriptDeclaration($js);
	}
	
	
	/**
	 * Function to create the tooltip text regardless of Joomla version
	 *
	 * @return 	string  : the HTML of the tooltip for usage in the title paramter of the HTML tag
	 * @since 1.5
	 */
	static function getToolTip($title = '', $content = '', $translate = 1, $escape = 1) {
		if (FLEXI_J30GE) {
			return JHtml::tooltipText($title, $content, $translate, $escape);
		}
		
		else {
			// Return empty in no title or content is given.
			if ($title == '' && $content == '') return '';
			
			// Pass texts through the JText.
			if ($translate) {
				$title = JText::_($title);
				$content = JText::_($content);
			}
			
			// Escape the texts.
			if ($escape) {
				$title = htmlspecialchars($title, ENT_COMPAT, 'UTF-8');
				$content = htmlspecialchars($content, ENT_COMPAT, 'UTF-8');
			}
			
			// Return only the title or content if no title or content is given respectively
			return $title.'::'.$content;
		}
	}
	
	
	static function escape($str) {
		return htmlspecialchars($str, ENT_COMPAT, 'UTF-8');
	}
	
	
	static function get_basedomain($url)
	{
		$pieces = parse_url($url);
		$domain = isset($pieces['host']) ? $pieces['host'] : '';   echo " ";
		if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
			return $regs['domain'];
		}
		return false;
	}

	static function is_safe_url($url, $baseonly=false)
	{
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$allowed_redirecturls = $cparams->get('allowed_redirecturls', 'internal_base');  // Parameter does not exist YET

		// prefix the URL if needed so that parse_url will work
		$has_prefix = preg_match("#^http|^https|^ftp#i", $url);
		$url = (!$has_prefix ? "http://" : "") . $url;

		// Require baseonly internal url: (HOST only)
		if ( $baseonly || $allowed_redirecturls == 'internal_base' )
			return flexicontent_html::get_basedomain($url) == flexicontent_html::get_basedomain(JURI::base());
		
		// Require full internal url: (HOST + this JOOMLA folder)
		else // if ( $allowed_redirecturls == 'internal_full' )
			return parse_url($url, PHP_URL_HOST) == parse_url(JURI::base(), PHP_URL_HOST);
		
		// Allow any URL, (external too) this may be considered a vulnerability for unlogged/logged users, since
		// users may be redirected to an offsite URL despite clicking an internal site URL received e.g. by an email
		//else
		//	return true;
	}


	/**
	 * Function to render the item view of a given item id
	 *
	 * @param 	int 		$item_id
	 * @return 	string  : the HTML of the item view, also the CSS / JS file would have been loaded
	 * @since 1.5
	 */
	function renderItem($item_id, $view=FLEXI_ITEMVIEW, $ilayout='') {
		require_once (JPATH_ADMINISTRATOR.DS.'components/com_flexicontent/defineconstants.php');
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		require_once("components/com_flexicontent/classes/flexicontent.fields.php");
		//require_once("components/com_flexicontent/classes/flexicontent.helper.php");
		require_once("components/com_flexicontent/models/".FLEXI_ITEMVIEW.".php");

		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		
		$itemmodel = FLEXI_J16GE ? new FlexicontentModelItem() : new FlexicontentModelItems();
		$item = $itemmodel->getItem($item_id, $check_view_access=false);

		$aid = FLEXI_J16GE ? JAccess::getAuthorisedViewLevels($user->id) : (int) $user->get('aid');
		list($item) = FlexicontentFields::getFields($item, $view, $item->parameters, $aid);
		
		// Get Item's specific ilayout
		if ($ilayout=='') {
			$ilayout = $item->parameters->get('ilayout', '');
		}
		// Get type's ilayout
		if ($ilayout=='') {
			$type = JTable::getInstance('flexicontent_types', '');
			$type->id = $item->type_id;
			$type->load();
			$type->params = FLEXI_J16GE ? new JRegistry($type->attribs) : new JParameter($type->attribs);
			$ilayout = $type->params->get('ilayout', 'default');
		}

		$this->item = & $item;
		$this->params_saved = @$this->params;
		$this->params = & $item->parameters;
		$this->tmpl = '.item.'.$ilayout;
		$this->print_link = JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&id='.$item->slug.'&pop=1&tmpl=component');
		$this->pageclass_sfx = '';
		if (!isset($this->item->event)) $this->item->event = new stdClass();
		$this->item->event->beforeDisplayContent = '';
		$this->item->event->afterDisplayTitle = '';
		$this->item->event->afterDisplayContent = '';
		$this->fields = & $this->item->fields;

		// start capturing output into a buffer
		ob_start();
		// Include the requested template filename in the local scope (this will execute the view logic).
		if ( file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout) )
			include JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'item.php';
		else if (file_exists(JPATH_COMPONENT.DS.'templates'.DS.$ilayout))
			include JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'item.php';
		else
			include JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.'default'.DS.'item.php';

		// done with the requested template; get the buffer and clear it.
		$item_html = ob_get_contents();
		ob_end_clean();
		$this->params = $this->params_saved;
		
		return $item_html;
	}


	static function limit_selector(&$params, $formname='adminForm', $autosubmit=1)
	{
		if ( !$params->get('limit_override') ) return '';

		$app	= JFactory::getApplication();
		//$orderby = $app->getUserStateFromRequest( $option.'.category'.$category->id.'.filter_order_Dir', 'filter_order', 'i.title', 'string' );
		$limit = $app->getUserStateFromRequest( 'limit', 'limit', $params->get('limit'), 'string' );

		flexicontent_html::loadFramework('select2');
		$classes  = "fc_field_filter use_select2_lib";
		$onchange = !$autosubmit ? '' : ' onchange="document.getElementById(\''.$formname.'\').submit();" ';
		$attribs  = ' class="'.$classes.'" ' . $onchange;
		
		$limit_options = $params->get('limit_options', '5,10,20,30,50,100,150,200');
		$limit_options = preg_split("/[\s]*,[\s]*/", $limit_options);

		$limiting = array();
		$limit_override_label = $params->get('limit_override_label', 2);
		//$limiting[] = JHTML::_('select.option', '', JText::_('Default'));
		$inside_label = $limit_override_label==2 ? ' '.JText::_('FLEXI_PER_PAGE') : '';
		//$default_limit = $params->get('limit');
		foreach($limit_options as $limit_option) {
			//$limit_isdefault = ($default_limit == $limit_option) ? ' ('.JText::_('FLEXI_DEFAULT').') ' : '';
			$limiting[] = JHTML::_('select.option', $limit_option, $limit_option .$inside_label /*.$limit_isdefault*/);
		}
		
		// Outside label
		$outside_label = '';
		if ($limit_override_label==1) {
			$outside_label = '<span class="flexi label limit_override_label">'.JText::_('FLEXI_PER_PAGE').'</span>';
		}
		return $outside_label.JHTML::_('select.genericlist', $limiting, 'limit', $attribs, 'value', 'text', $limit );
	}

	static function ordery_selector(&$params, $formname='adminForm', $autosubmit=1, $extra_order_types=array(), $sfx='')
	{
		if ( !$params->get('orderby_override'.$sfx, 0) ) return '';

		$app	= JFactory::getApplication();
		//$orderby = $app->getUserStateFromRequest( $option.'.category'.$category->id.'.filter_order_Dir', 'filter_order', 'i.title', 'string' );
		$orderby = $app->getUserStateFromRequest( 'orderby', 'orderby', ''/*$params->get('orderby'.$sfx)*/, 'string' );

		flexicontent_html::loadFramework('select2');
		$classes  = "fc_field_filter use_select2_lib";
		$onchange = !$autosubmit ? '' : ' onchange="document.getElementById(\''.$formname.'\').submit();" ';
		$attribs  = ' class="'.$classes.'" ' . $onchange;
		
		$orderby_options = $params->get('orderby_options'.$sfx, array('_preconfigured_','date','rdate','modified','alpha','ralpha','author','rauthor','hits','rhits','id','rid','order'));
		$orderby_options = FLEXIUtilities::paramToArray($orderby_options);

		$orderby_names =array('_preconfigured_'=>'FLEXI_ORDER_DEFAULT_INITIAL',
		'date'=>'FLEXI_ORDER_OLDEST_FIRST','rdate'=>'FLEXI_ORDER_MOST_RECENT_FIRST',
		'modified'=>'FLEXI_ORDER_LAST_MODIFIED_FIRST', 'published'=>'FLEXI_ORDER_RECENTLY_PUBLISHED_FIRST',
		'alpha'=>'FLEXI_ORDER_TITLE_ALPHABETICAL','ralpha'=>'FLEXI_ORDER_TITLE_ALPHABETICAL_REVERSE',
		'author'=>'FLEXI_ORDER_AUTHOR_ALPHABETICAL','rauthor'=>'FLEXI_ORDER_AUTHOR_ALPHABETICAL_REVERSE',
		'hits'=>'FLEXI_ORDER_MOST_HITS','rhits'=>'FLEXI_ORDER_LEAST_HITS',
		'id'=>'FLEXI_ORDER_HIGHEST_ITEM_ID','rid'=>'FLEXI_ORDER_LOWEST_ITEM_ID',
		'commented'=>'FLEXI_ORDER_MOST_COMMENTED', 'rated'=>'FLEXI_ORDER_BEST_RATED',
		'order'=>'FLEXI_ORDER_CONFIGURED_ORDER');

		$ordering = array();
		foreach ($extra_order_types as $value => $text) {
			$text = JText::_( $text );
			$ordering[] = JHTML::_('select.option',  $value,  $text);
		}
		foreach ($orderby_options as $orderby_option) {
			if ($orderby_option=='__SAVED__') continue;
			$value = ($orderby_option!='_preconfigured_') ? $orderby_option : '';
			$text = JText::_( $orderby_names[$orderby_option] );
			$ordering[] = JHTML::_('select.option',  $value,  $text);
		}

		return JHTML::_('select.genericlist', $ordering, 'orderby', $attribs, 'value', 'text', $orderby );
	}
	
	
	static function searchphrase_selector(&$params, $formname='adminForm') {
		$searchphrase = '';
		if($show_searchphrase = $params->get('show_searchphrase', 1)) {
			$default_searchphrase = $params->get('default_searchphrase', 'all');
			$searchphrase = JRequest::getVar('searchphrase', $default_searchphrase);
			$searchphrase_names = array('natural'=>'FLEXI_NATURAL_PHRASE', 'natural_expanded'=>'FLEXI_NATURAL_PHRASE_GUESS_RELEVANT', 
				'all'=>'FLEXI_ALL_WORDS', 'any'=>'FLEXI_ANY_WORDS', 'exact'=>'FLEXI_EXACT_PHRASE');
		
			$searchphrases = array();
			foreach ($searchphrase_names as $searchphrase_value => $searchphrase_name) {
				$_obj = new stdClass();
				$_obj->value = $searchphrase_value;
				$_obj->text  = $searchphrase_name;
				$searchphrases[] = $_obj;
			}
			$searchphrase = JHTML::_('select.genericlist', $searchphrases, 'searchphrase',
				'class="fc_field_filter use_select2_lib"', 'value', 'text', $searchphrase, 'searchphrase', $_translate=true);
		}
		return $searchphrase;
	}
	
	
	/**
	 * Utility function to add JQuery to current Document
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function loadJQuery( $add_jquery = 1, $add_jquery_ui = 1, $add_jquery_ui_css = 1, $add_remote = 1, $params = null )
	{
		static $jquery_added = false;
		static $jquery_ui_added = false;
		static $jquery_ui_css_added = false;
		$document = JFactory::getDocument();
		
		// Set jQuery to load in views that use it
		$JQUERY_VER    = !$params ? '1.8.3' : $params->get('jquery_ver', '1.8.3');
		$JQUERY_UI_VER = !$params ? '1.9.2' : $params->get('jquery_ui_ver', '1.9.2');
		$JQUERY_UI_THEME = !$params ? 'ui-lightness' : $params->get('jquery_ui_theme', 'ui-lightness');
		$add_remote = (FLEXI_J30GE && $add_remote==2) || (!FLEXI_J30GE && $add_remote);
		
		
		// **************
		// jQuery library
		// **************
		
		if ( $add_jquery && !$jquery_added && !JPluginHelper::isEnabled('system', 'jquerysupport') )
		{
			if ( $add_remote ) {
				$document->addScript('//ajax.googleapis.com/ajax/libs/jquery/'.$JQUERY_VER.'/jquery.min.js');
			} else {
				FLEXI_J30GE ?
					JHtml::_('jquery.framework') :
					$document->addScript(JURI::root(true).'/components/com_flexicontent/librairies/jquery/js/jquery-'.$JQUERY_VER.'.min.js');
			}
			// The 'noConflict()' statement must be inside a js file, to make sure it executed immediately
			if (!FLEXI_J30GE) $document->addScript(JURI::root(true).'/components/com_flexicontent/librairies/jquery/js/jquery-no-conflict.js');
			//$document->addCustomTag('<script>jQuery.noConflict();</script>');  // not placed in proper place
			$jquery_added = 1;
		}
		
		
		// *******************************
		// jQuery-UI library (and its CSS)
		// *******************************
		
		if ( $add_jquery_ui && !$jquery_ui_added ) {
			// Load all components of jQuery-UI
			if ($add_remote) {
				$document->addScript('//ajax.googleapis.com/ajax/libs/jqueryui/'.$JQUERY_UI_VER.'/jquery-ui.min.js');
			} else {
				if (FLEXI_J30GE) {
					JHtml::_('jquery.ui', array('core', 'sortable'));   // 'core' in J3+ includes all parts of jQuery-UI CORE component: Core, Widget, Mouse, Position
					$document->addScript(JURI::root(true).'/components/com_flexicontent/librairies/jquery/js/jquery-ui/jquery.ui.dialog.min.js');
					$document->addScript(JURI::root(true).'/components/com_flexicontent/librairies/jquery/js/jquery-ui/jquery.ui.menu.min.js');
					$document->addScript(JURI::root(true).'/components/com_flexicontent/librairies/jquery/js/jquery-ui/jquery.ui.autocomplete.min.js');
				} else {
					$document->addScript(JURI::root(true).'/components/com_flexicontent/librairies/jquery/js/jquery-ui-'.$JQUERY_UI_VER.'.js');
				}
			}
			$jquery_ui_added = 1;
		}
		
		// Add jQuery UI theme, this is included in J3+ when executing jQuery-UI framework is called
		if ( $add_jquery_ui_css && !$jquery_ui_css_added ) {
			// FLEXI_JQUERY_UI_CSS_STYLE:  'ui-lightness', 'smoothness'
			if ($add_remote) {
				$document->addStyleSheet('//ajax.googleapis.com/ajax/libs/jqueryui/'.$JQUERY_UI_VER.'/themes/'.$JQUERY_UI_THEME.'/jquery-ui.css');
			} else {
				$document->addStyleSheet(JURI::root(true).'/components/com_flexicontent/librairies/jquery/css/'.$JQUERY_UI_THEME.'/jquery-ui-'.$JQUERY_UI_VER.'.css');
				$jquery_ui_css_added = 1;
			}
		}
	}
	
	
	/**
	 * Utility function to get the Mobile Detector Object
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function getMobileDetector()
	{
		static $mobileDetector = null;
		
		if ( $mobileDetector===null ) {
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'mobiledetect'.DS.'Mobile_Detect.php');
			$mobileDetector = new Mobile_Detect_FC();
		}
		
		return $mobileDetector;
	}
	
	
	/**
	 * Utility function to load each JS Frameworks once
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function loadFramework( $framework, $mode='' )
	{
		// Detect already loaded framework
		static $_loaded = array();
		if ( isset($_loaded[$framework]) ) return $_loaded[$framework];
		$_loaded[$framework] = false;
		
		// Get frameworks that are configured to be loaded manually in frontend (e.g. via the Joomla template)
		$app = JFactory::getApplication();
		static $load_frameworks = null;
		static $load_jquery = null;
		if ( !isset($load_frameworks[$framework]) ) {
			$flexiparams = JComponentHelper::getParams('com_flexicontent');
			//$load_frameworks = $flexiparams->get('load_frameworks', array('jQuery','image-picker','masonry','select2','inputmask','prettyCheckable','fancybox'));
			//$load_frameworks = FLEXIUtilities::paramToArray($load_frameworks);
			//$load_frameworks = array_flip($load_frameworks);
			//$load_jquery = isset($load_frameworks['jQuery']) || !$app->isSite();
			if ( $load_jquery===null ) $load_jquery = $flexiparams->get('loadfw_jquery', 1)==1  ||  !$app->isSite();
			$load_framework = $flexiparams->get( 'loadfw_'.strtolower(str_replace('-','_',$framework)), 1 );
			$load_frameworks[$framework] = $load_framework==1  ||  ($load_framework==2 && !$app->isSite());
		}
		
		// Set loaded flag
		$_loaded[$framework] = $load_frameworks[$framework];
		// Do not progress further if it is disabled
		if ( !$load_frameworks[$framework] ) return false;
		
		// Load Framework
		$document = JFactory::getDocument();
		$js = "";
		$css = "";
		switch ( $framework )
		{
			case 'jQuery':
				if ($load_jquery) flexicontent_html::loadJQuery();
				break;
			
			case 'mCSB':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/mCSB';
				$document->addScript($framework_path.'/jquery.mCustomScrollbar.min.js');
				$document->addStyleSheet($framework_path.'/jquery.mCustomScrollbar.css');
				$js .= "
					jQuery(document).ready(function(){
					    jQuery('.fc_add_scroller').mCustomScrollbar({
					    	theme:'dark-thick',
					    	advanced:{updateOnContentResize: true}
					    });
					    jQuery('.fc_add_scroller_horizontal').mCustomScrollbar({
					    	theme:'dark-thick',
					    	horizontalScroll:true,
					    	advanced:{updateOnContentResize: true}
					    });
					});
				";
				break;
			
			case 'image-picker':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/image-picker';
				$document->addScript($framework_path.'/image-picker.min.js');
				$document->addStyleSheet($framework_path.'/image-picker.css');
				break;
			
			case 'masonry':
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/masonry';
				$document->addScript($framework_path.'/masonry.pkgd.min.js');
				
				break;
			
			case 'select2':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/select2';
				$framework_folder = JPATH_SITE .DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'select2';
				$document->addScript($framework_path.'/select2.min.js');
				$document->addStyleSheet($framework_path.'/select2.css');
				
				$user_lang = flexicontent_html::getUserCurrentLang();
				if ( $user_lang && $user_lang!='en' )
				{
					// Try language shortcode
					if ( file_exists($framework_folder.DS.'select2_locale_'.$user_lang.'.js') ) {
						$document->addScript($framework_path.'/select2_locale_'.$user_lang.'.js');
					}
					// Try coutry language code
					else {
						$languages = FLEXIUtilities::getLanguages($hash='shortcode');
						$lang_code = isset($languages->$user_lang->code) ? $languages->$user_lang->code : false;
						if ( $lang_code && file_exists($framework_folder.DS.'select2_locale_'.$lang_code.'.js') ) {
							$document->addScript($framework_path.'/select2_locale_'.$lang_code.'.js');
						}
					}
				}
				
				$js .= "
					jQuery(document).ready(function() {
						
						"/* Attach select2 to specific to select elements having specific CSS class, show selected values as both: unselectable and disabled */."
						jQuery('select.use_select2_lib').select2({
							/*hideSelectionFromResult: function(selectedObject) { selectedObject.removeClass('select2-result-selectable').addClass('select2-result-unselectable').addClass('select2-disabled'); return false; },*/
							minimumResultsForSearch: 10
						});
						
						jQuery('div.use_select2_lib').each(function() {
							var el_container = jQuery(this);
							var el_select = el_container.next('select');
							
							"/* MULTI-SELECT2: Initialize internal labels, placing the label so that it overlaps the text filter box */."
							var fc_label_text = el_select.attr('data-fc_label_text');
							if (!fc_label_text) fc_label_text = el_select.attr('fc_label_text');
							if (fc_label_text) {
								var _label = (fc_label_text.length >= 30) ? fc_label_text.substring(0, 28) + '...' : fc_label_text;
								
								jQuery('<span/>', {
									'class': 'fc_has_inner_label fc_has_inner_label_select2',
									'text': _label
								}).prependTo(el_container.find('.select2-search-field'));
							}
							
							"/* MULTI-SELECT2: Initialize internal prompts, placing the prompt so that it overlaps the text filter box */."
							var fc_prompt_text = el_select.attr('data-fc_prompt_text');
							if (!fc_prompt_text) fc_prompt_text = el_select.attr('fc_prompt_text');
							if (fc_prompt_text) {
								var _prompt = (fc_prompt_text.length >= 30) ? fc_prompt_text.substring(0, 28) + '...' : fc_prompt_text;
								
								jQuery('<span/>', {
									'class': 'fc_has_inner_prompt fc_has_inner_prompt_select2',
									'text': _prompt
								}).prependTo(el_container.find('.select2-search-field')).hide();
							}
							
							"/* SINGLE-SELECT2: Highlight selects with an active value */."
							if ( ! el_select.attr('multiple') && !el_select.hasClass('fc_skip_highlight') ) {
								var el = el_container.find('.select2-choice');
								var val = el_select.val();
								if (val === null) {
									//el.addClass('fc_highlight_disabled');
								} else if (val.length) {
									el.addClass('fc_highlight');
								} else {
									el.removeClass('fc_highlight');
								}
							}
						});
						
						"/* MULTI-SELECT2: */."
						jQuery('select.use_select2_lib').on('select2-open', function() {
							"/* Add events to handle focusing the text filter box (hide inner label) */."
							var el_container = jQuery(this).parent();
							var el = jQuery(this).parent().find('.select2-input');
							var el_label = el.prevAll('.fc_has_inner_label');
							if (el_label) el_label.hide();
							var el_prompt = el.prevAll('.fc_has_inner_prompt');
							if (el_prompt) el_prompt.show();
							
							"/* Allow listing already selected options WHEN having class 'select2_list_selected' */."
							if (jQuery(this).hasClass('select2_list_selected')) {
								var els = jQuery('#select2-drop').find('.select2-selected');
								els.addClass('select2-selected-highlight').addClass('select2-disabled').removeClass('select2-selected').removeClass('select2-result-selectable');
							}
						}).on('select2-close', function() {
							"/* Add events to handle bluring the text filter box (show inner label) */."
							var el_container = jQuery(this).parent();
							var el = jQuery(this).parent().find('.select2-input');
							var el_label = el.prevAll('.fc_has_inner_label');
							if (el_label) el_label.show();
							var el_prompt = el.prevAll('.fc_has_inner_prompt');
							if (el_prompt) el_prompt.hide();
							
							"/* Restore already selected options state */."
							if (jQuery(this).hasClass('select2_list_selected')) {
								var els = jQuery('#select2-drop').find('.select2-selected-highlight');
								els.removeClass('select2-selected-highlight').removeClass('select2-disabled').addClass('select2-result-selectable');
							}
						}).on
						
						"/* SINGLE-SELECT2: Add events to handle highlighting selected value */."
						('change', function() {
							var el_select = jQuery(this);
							if ( ! el_select.attr('multiple') && !el_select.hasClass('fc_skip_highlight') ) {
								var el = jQuery(this).prev('div').find('.select2-choice');
								var val = el_select.val();
								if (val.length) {
									el.addClass('fc_highlight');
								} else {
									el.removeClass('fc_highlight');
								}
							}
						});
						
						"/* SINGLE-SELECT2: Add events to handle highlighting selected value */."
						jQuery('div.use_select2_lib.select2-container-multi input').on('keydown', function() {
							var el = jQuery(this);
							setTimeout(function() {
								if (el.val().length) {
									var el_prompt = el.prevAll('.fc_has_inner_prompt');
									if (el_prompt) el_prompt.hide();
								} else {
									var el_prompt = el.prevAll('.fc_has_inner_prompt');
									if (el_prompt) el_prompt.show();
								}
							}, 0);
						});
						
						"/* SELECT2: scrollbar wrap problem */."
						jQuery('select.use_select2_lib').on('loaded open', function() {
							var ul = jQuery('#select2-drop ul.select2-results');
							var needsScroll= ul.prop('scrollHeight') > ul.prop('clientHeight');
							if (needsScroll) ul.css('overflow-y', 'scroll');
							else  ul.css('overflow-y', 'auto');
						});
						
					});
				";
				break;
			
			case 'inputmask':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/inputmask';
				$document->addScript($framework_path.'/jquery.inputmask.bundle.min.js');
				
				// Extra inputmask declarations definitions, e.g. ...
				/*$js .= "
					jQuery.extend(jQuery.inputmask.defaults.definitions, {
					    'f': {
					        \"validator\": \"[0-9\(\)\.\+/ ]\",
					        \"cardinality\": 1,
					        'prevalidator': null
					    }
					});
				";*/
				
				// Attach inputmask to all input fields that have appropriate tag parameters
				$js .= "
					jQuery(document).ready(function(){
					    jQuery('input.has_inputmask').inputmask();
					    jQuery('input.inputmask-regex').inputmask('Regex');
					});
				";
				break;
			
			case 'prettyCheckable':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/prettyCheckable';
				$document->addScript($framework_path.'/dev/prettyCheckable.js');
				$document->addStyleSheet($framework_path.'/dist/prettyCheckable.css');
				$js .= "
					jQuery(document).ready(function(){
						jQuery('input.use_prettycheckable').each(function() {
							var elem = jQuery(this);
							var lbl = elem.next('label');
							var lbl_html = elem.next('label').html();
							lbl.remove();
							elem.prettyCheckable({
								color: 'blue',
								label: lbl_html
							});
						});
					});
				";
				break;
			
			case 'multibox':
			case 'jmultibox':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/jmultibox';
				
				// Add JS
				$document->addScript($framework_path.'/js/jmultibox.js');
				$document->addScript($framework_path.'/js/jquery.vegas.js');
				
				// Add CSS
				$document->addStyleSheet($framework_path.'/styles/multibox.css');
				$document->addStyleSheet($framework_path.'/styles/jquery.vegas.css');
				if (substr($_SERVER['HTTP_USER_AGENT'],0,34)=="Mozilla/4.0 (compatible; MSIE 6.0;") {
					$document->addStyleSheet($framework_path.'/styles/multibox-ie6.css');
				}
				
				// Attach multibox to ... this will be left to the caller so that it will create a multibox object with custom options
				//$js .= "";
				break;

			case 'fancybox':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/fancybox';
				
				// Add mousewheel plugin (this is optional)
				$document->addScript($framework_path.'/lib/jquery.mousewheel-3.0.6.pack.js');
				
				// Add fancyBox CSS / JS
				$document->addStyleSheet($framework_path.'/source/jquery.fancybox.css?v=2.1.1');
				$document->addScript($framework_path.'/source/jquery.fancybox.pack.js?v=2.1.1');
				
				// Optionally add helpers - button, thumbnail and/or media
				$document->addStyleSheet($framework_path.'/source/helpers/jquery.fancybox-buttons.css?v=1.0.4');
				$document->addScript($framework_path.'/source/helpers/jquery.fancybox-buttons.js?v=1.0.4');
				$document->addScript($framework_path.'/source/helpers/jquery.fancybox-media.js?v=1.0.4');
				$document->addStyleSheet($framework_path.'/source/helpers/jquery.fancybox-thumbs.css?v=1.0.7');
				$document->addScript($framework_path.'/source/helpers/jquery.fancybox-thumbs.js?v=1.0.7');
				
				// Attach fancybox to all elements having a specific CSS class
				$js .= "
					jQuery(document).ready(function(){
						jQuery('.fancybox').fancybox();
					});
				";
				break;
			
			case 'galleriffic':
				if ($load_jquery) flexicontent_html::loadJQuery();
				//flexicontent_html::loadFramework('fancybox');
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/galleriffic';
				//$document->addStyleSheet($framework_path.'/css/basic.css');  // This is too generic and should not be loaded
				$document->addStyleSheet($framework_path.'/css/galleriffic-3.css');
				$document->addScript($framework_path.'/js/jquery.galleriffic.js');
				$document->addScript($framework_path.'/js/jquery.opacityrollover.js');
				
				break;
			
			case 'elastislide':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/elastislide';
				$document->addStyleSheet($framework_path.'/css/demo.css');
				$document->addStyleSheet($framework_path.'/css/style.css');
				$document->addStyleSheet($framework_path.'/css/elastislide.css');
				
				$document->addScript($framework_path.'/js/jquery.tmpl.min.js');
				$document->addScript($framework_path.'/js/jquery.easing.1.3.js');
				$document->addScript($framework_path.'/js/jquery.elastislide.js');
				//$document->addScript($framework_path.'/js/gallery.js'); // replace with field specific: gallery_tmpl.js
				break;
			
			case 'photoswipe':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/photoswipe';
				
				//$document->addStyleSheet($framework_path.'/lib/jquery.mobile/jquery.mobile.css');
				$document->addStyleSheet($framework_path.'/photoswipe.css');
				
				//$document->addScript($framework_path.'/lib/jquery.mobile/jquery.mobile.js');
				$document->addScript($framework_path.'/lib/simple-inheritance.min.js');
				//$document->addScript($framework_path.'/lib/jquery.animate-enhanced.min.js');
				$document->addScript($framework_path.'/code.photoswipe.min.js');
				
				$js .= "
				jQuery(document).ready(function() {
					var myPhotoSwipe = jQuery('.photoswipe_fccontainer a').photoSwipe(); 
				});
				";
				break;
			
			case 'fcxSlide':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/fcxSlide';
				$document->addScript($framework_path.'/class.fcxSlide.js');
				$document->addStyleSheet($framework_path.'/fcxSlide.css');
				//$document->addScript($framework_path.'/class.fcxSlide.packed.js');
				break;
			
			case 'imagesLoaded':
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/imagesLoaded';
				$document->addScript($framework_path.'/imagesloaded.pkgd.min.js');
				break;
			
			case 'noobSlide':
				// Make sure mootools are loaded
				FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/noobSlide';
				//$document->addScript($framework_path.'/_class.noobSlide.js');
				$document->addScript($framework_path.'/_class.noobSlide.packed.js');
				break;
			
			case 'zTree':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/zTree';
				$document->addStyleSheet($framework_path.'/css/flexi_ztree.css');
				$document->addStyleSheet($framework_path.'/css/zTreeStyle/zTreeStyle.css');
				$document->addScript($framework_path.'/js/jquery.ztree.all-3.5.min.js');
				//$document->addScript($framework_path.'/js/jquery.ztree.core-3.5.js');
				//$document->addScript($framework_path.'/js/jquery.ztree.excheck-3.5.js');
				//$document->addScript($framework_path.'/js/jquery.ztree.exedit-3.5.js');
				break;
			
			
			case 'plupload':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$framework_path = JURI::root(true).'/components/com_flexicontent/librairies/plupload';
				$document->addScript($framework_path.'/js/plupload.full.min.js');
				
				if ($mode=='ui') {
					$document->addStyleSheet($framework_path.'/js/jquery.ui.plupload/css/jquery.ui.plupload.css');
					$document->addScript($framework_path.'/js/jquery.ui.plupload/jquery.ui.plupload.min.js');
					$document->addScript($framework_path.'/js/themeswitcher.js');
				} else {
					$document->addStyleSheet($framework_path.'/js/jquery.plupload.queue/css/jquery.plupload.queue.css');
					$document->addScript($framework_path.'/js/jquery.plupload.queue/jquery.plupload.queue.js');
				}
				// For debugging
				//$document->addScript($framework_path.'/js/moxie.min.js');
				//$document->addScript($framework_path.'/js/plupload.dev.js');
				break;
			
			case 'flexi_tmpl_common':
				if ($load_jquery) flexicontent_html::loadJQuery();
				flexicontent_html::loadFramework('select2');  // make sure select2 is loaded
				
				$js .= "
					var _FC_GET = ".json_encode($_GET).";
				";
				//var _FC_POST = ".json_encode($_POST).";
				//var _FC_REQUEST = ".json_encode($_REQUEST).";
				$document->addScript( JURI::root(true).'/components/com_flexicontent/assets/js/tmpl-common.js' );
				FLEXI_J16GE ? JText::script("FLEXI_APPLYING_FILTERING", true) : fcjsJText::script("FLEXI_APPLYING_FILTERING", true);
				FLEXI_J16GE ? JText::script("FLEXI_TYPE_TO_LIST", true) : fcjsJText::script("FLEXI_TYPE_TO_LIST", true);
				FLEXI_J16GE ? JText::script("FLEXI_TYPE_TO_FILTER", true) : fcjsJText::script("FLEXI_TYPE_TO_FILTER", true);
				break;
			
			case 'flexi-lib':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$document->addScript( JURI::root(true).'/components/com_flexicontent/assets/js/flexi-lib.js' );
				FLEXI_J16GE ? JText::script("FLEXI_NOT_AN_IMAGE_FILE", true) : fcjsJText::script("FLEXI_NOT_AN_IMAGE_FILE", true);
				break;
			
			default:
				JFactory::getApplication()->enqueueMessage(__FUNCTION__.' Cannot load unknown Framework: '.$framework, 'error');
				break;
		}
		
		// Add custom JS & CSS code
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		return $_loaded[$framework];
	}
	
	
	/**
	 * Escape a string so that it can be used directly by JS source code
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function escapeJsText($string, $skipquote='')
	{
		$string = (string)$string;
		$string = str_replace("\r", '', $string);
		$string = addcslashes($string, "\0..\37'\\");
		// Whether to skip single or double quotes
		if ( $skipquote!='d' )  $string = str_replace('"', '\"', $string);
		if ( $skipquote!='s' )  $string = str_replace("'", "\'", $string);
		$string = str_replace("\n", ' ', $string);
		return $string;
	}

	/**
	 * Trims whitespace from an array of strings
	 *
	 * @param 	string array			$arr_str
	 * @return 	string array
	 * @since 1.5
	 */
	static function arrayTrim($arr_str) {
		if(!is_array($arr_str)) return false;
		foreach($arr_str as $k=>$a) {
			$arr_str[$k] = trim($a);
		}
		return $arr_str;
	}
	
	
	// Server-Side validation
	static function dataFilter( $v, $maxlength=0, $validation='string', $check_callable=0 )
	{
		if ($validation=='-1') return flexicontent_html::striptagsandcut( $v, $maxlength );
		
		$v = $maxlength ? substr($v, 0, $maxlength) : $v;
		if ($check_callable) {
			if (strpos($validation, '::') !== false && is_callable(explode('::', $validation)))
				return call_user_func(explode('::', $validation), $v);   // A callback class method
			
			elseif (function_exists($validation))
				return call_user_func($validation, $v);  // A callback function
		}
		
		// Do filtering 
		if ($validation=='1') $safeHtmlFilter = JFilterInput::getInstance(null, null, 1, 1);
		else if ($validation!='2') $noHtmlFilter = JFilterInput::getInstance();
		switch ($validation) {
			case  '1':
				// Allow safe HTML
				$v = $safeHtmlFilter->clean($v, 'string');
				break;
				
			case  '2':
				// Filter according to user group Text Filters
				$v = JComponentHelper::filterText($v);
				break;
				
			case 'URL': case 'url':
				// This cleans some of the more dangerous characters but leaves special characters that are valid.
				$v = trim($noHtmlFilter->clean($v, 'HTML'));
				
				// <>" are never valid in a uri see http://www.ietf.org/rfc/rfc1738.txt.
				$v = str_replace(array('<', '>', '"'), '', $v);
				
				// Convert to Punycode string
				$v = FLEXI_J30GE ? JStringPunycode::urlToPunycode( $v ) : $v;
				break;
				
			case 'EMAIL': case 'email':
				// This cleans some of the more dangerous characters but leaves special characters that are valid.
				$v = trim($noHtmlFilter->clean($v, 'HTML'));
				
				// <>" are never valid in a email ?
				$v = str_replace(array('<', '>', '"'), '', $v);
				
				// Convert to Punycode string
				$v = FLEXI_J30GE ? JStringPunycode::emailToPunycode( $v ) : $v;
				
				// Check for valid email (punycode is ASCII so this should work with UTF-8 too)
				$email_regexp = "/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/";
				if (!preg_match($email_regexp, $v)) $v = '';
				break;
				
			default:
				// Filter using JFilterInput
				$v = $noHtmlFilter->clean($v, $validation);
				break;
		}
		
		$v = trim($v);
		return $v;
	}
	
	
	/**
	 * Strip html tags and cut after x characters
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function striptagsandcut( $text, $chars=null )
	{
		// Convert html entities to characters so that they will not be removed ... by strip_tags
		$text = html_entity_decode ($text, ENT_NOQUOTES, 'UTF-8');
		
		// Strip SCRIPT tags AND their containing code
		$text = preg_replace( '#<script\b[^>]*>(.*?)<\/script>#is', '', $text );
		
		// Add whitespaces at start/end of tags so that words will not be joined,
		//$text = preg_replace('/(<\/[^>]+>((?!\P{L})|(?=[0-9])))|(<[^>\/][^>]*>)/u', ' $1', $text);
		$text = preg_replace('/(<\/[^>]+>(?![\:|\.|,|:|"|\']))|(<[^>\/][^>]*>)/u', ' $1', $text);
		
		// Strip html tags
		$cleantext = strip_tags($text);

		// clean additionnal plugin tags
		$patterns = array();
		$patterns[] = '#\[(.*?)\]#';
		$patterns[] = '#{(.*?)}#';
		$patterns[] = '#&(.*?);#';
		
		foreach ($patterns as $pattern) {
			$cleantext = preg_replace( $pattern, '', $cleantext );
		}
		
		// Replace multiple spaces, tabs, newlines, etc with a SINGLE whitespace so that text length will be calculated correctly
		$cleantext = preg_replace('/[\p{Z}\s]{2,}/u', ' ', $cleantext);  // Unicode safe whitespace replacing
		
		// Calculate length according to UTF-8 encoding
		$length = JString::strlen($cleantext);
		
		// Cut off the text if required but reencode html entities before doing so
		if ($chars) {
			if ($length > $chars) {
				$cleantext = JString::substr( $cleantext, 0, $chars ).'...';
			}
		}
		
		// Reencode HTML special characters, (but do not encode UTF8 characters)
		$cleantext = htmlspecialchars($cleantext, ENT_QUOTES, 'UTF-8');
		
		return $cleantext;
	}

	/**
	 * Make image tag from field or extract image from introtext
	 *
	 * @param 	array 		$row
	 * @return 	string
	 * @since 1.5
	 */
	static function extractimagesrc( $row )
	{
		jimport('joomla.filesystem.file');

		$regex = '#<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1#im';

		preg_match ($regex, $row->introtext, $matches);

		if(!count($matches)) preg_match ($regex, $row->fulltext, $matches);

		$images = (count($matches)) ? $matches : array();

		$image = '';
		if (count($images)) $image = $images[2];

		if (!preg_match("#^http|^https|^ftp#i", $image)) {
			// local file check that it exists
			$image = JFile::exists( JPATH_SITE . DS . $image ) ? $image : '';
		}

		return $image;
	}


	/**
	 * Logic to change the state of an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	static function setitemstate( $controller_obj )
	{
		$id = JRequest::getInt( 'id', 0 );
		JRequest::setVar( 'cid', $id );

		$app = JFactory::getApplication();
		$modelname = $app->isAdmin() ? 'item' : FLEXI_ITEMVIEW;
		$model = $controller_obj->getModel( $modelname );
		$user = JFactory::getUser();
		$state = JRequest::getVar( 'state', 0 );

		// Get owner and other item data
		$db = JFactory::getDBO();
		$q = "SELECT id, created_by, catid FROM #__content WHERE id =".$id;
		$db->setQuery($q);
		$item = $db->loadObject();

		// Determine priveleges of the current user on the given item
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
			$has_delete     = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $item->created_by == $user->get('id'));
			// ...
			$permission = FlexicontentHelperPerm::getPerm();
			$has_archive    = $permission->CanArchives;
		} else if ($user->gid >= 25) {
			$has_edit_state = true;
			$has_delete     = true;
			$has_archive    = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit_state = in_array('publish', $rights) || (in_array('publishown', $rights) && $item->created_by == $user->get('id')) ;
			$has_delete     = in_array('delete', $rights) || (in_array('deleteown', $rights) && $item->created_by == $user->get('id')) ;
			$has_archive    = FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid);
		} else {
			$has_edit_state = $user->authorize('com_content', 'publish', 'content', 'all');
			$has_delete     = $user->gid >= 23; // is at least manager
			$has_archive    = $user->gid >= 23; // is at least manager
		}

		$has_edit_state = $has_edit_state && in_array($state, array(0,1,-3,-4,-5));
		$has_delete     = $has_delete     && $state == -2;
		$has_archive    = $has_archive    && $state == (FLEXI_J16GE ? 2:-1);

		// check if user can edit.state of the item
		$access_msg = '';
		if ( !$has_edit_state && !$has_delete && !$has_archive )
		{
			//echo JText::_( 'FLEXI_NO_ACCESS_CHANGE_STATE' );
			echo JText::_( 'FLEXI_DENIED' );   // must a few words
			return;
		}
		else if(!$model->setitemstate($id, $state))
		{
			$msg = JText::_('FLEXI_ERROR_SETTING_THE_ITEM_STATE');
			echo $msg . ": " .$model->getError();
			return;
		}

		// Clean cache
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache($group='', 0);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
			$cache = FLEXIUtilities::getCache($group='', 1);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
		} else {
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();
		}

		// Output new state icon and terminate
		$tmpparams = FLEXI_J16GE ? new JRegistry() : new JParameter("");
		$tmpparams->set('stateicon_popup', 'basic');
		$stateicon = flexicontent_html::stateicon( $state, $tmpparams );
		echo $stateicon;
		exit;
	}


	/**
	 * Creates the rss feed button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function feedbutton($view, &$params, $slug = null, $itemslug = null, $item = null)
	{
		if ( !$params->get('show_feed_icon', 1) || JRequest::getCmd('print') ) return;
		
		$uri    = JURI::getInstance();
		$base  	= $uri->toString( array('scheme', 'host', 'port'));

		//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
		if($view == 'category') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getCategoryRoute($slug).'&format=feed&type=rss');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&format=feed&type=rss', false );
		} elseif($view == FLEXI_ITEMVIEW) {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($itemslug, $slug, 0, $item).'&format=feed&type=rss');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug.'&format=feed&type=rss', false );
		} elseif($view == 'tags') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($itemslug).'&format=feed&type=rss');
			//$link = $base.JRoute::_( 'index.php?view='.$view.'&id='.$slug.'&format=feed&type=rss', false );
		} else {
			$link = $base . JRoute::_( 'index.php?view='.$view.'&format=feed&type=rss', false );
		}
		
		$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';
		
		// This checks template image directory for image, if none found, default image is returned
		$show_icons = $params->get('show_icons');
		if ( $show_icons ) {
			$attribs = '';
			$image = FLEXI_J16GE ?
				JHTML::image(FLEXI_ICONPATH.'livemarks.png', JText::_( 'FLEXI_FEED' ), $attribs) :
				JHTML::_('image.site', 'livemarks.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_FEED' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib = JText::_( 'FLEXI_FEED_TIP' );
		$text = JText::_( 'FLEXI_FEED' );
		
		$button_classes = 'fc_feedbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
		} else {
			$caption = $text;
			$button_classes .= FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
		}
		$button_classes .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		// $link as set above
		$output	= '<a href="'.$link.'" class="'.$button_classes.'" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );

		return $output;
	}

	/**
	 * Creates the print button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function printbutton( $print_link, &$params )
	{
		if ( !$params->get('show_print_icon') || JRequest::getCmd('print') ) return;
		
		$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		
		if ( JRequest::getInt('pop') ) {
			$onclick = ' window.print(); return false; ';
			$link = 'javascript:;';
		} else {
			$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';
			$link = JRoute::_($print_link);
		}
		
		// This checks template image directory for image, if none found, default image is returned
		$show_icons = $params->get('show_icons');
		if ( $show_icons ) {
			$attribs = '';
			$image = FLEXI_J16GE ?
				JHTML::image(FLEXI_ICONPATH.'printButton.png', JText::_( 'FLEXI_PRINT' ), $attribs) :
				JHTML::_('image.site', 'printButton.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_PRINT' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib = JText::_( 'FLEXI_PRINT_TIP' );
		$text = JText::_( 'FLEXI_PRINT' );
		
		$button_classes = 'fc_printbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
		} else {
			$caption = $text;
			$button_classes .= FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
		}
		$button_classes .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		// $link as set above
		$output	= '<a href="'.$link.'" class="'.$button_classes.'" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );

		return $output;
	}

	/**
	 * Creates the email button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function mailbutton($view, &$params, $slug = null, $itemslug = null, $item = null)
	{
		static $initialize = null;
		static $uri, $base;

		if ( !$params->get('show_email_icon') || JRequest::getCmd('print') ) return;

		if ($initialize === null) {
			if (file_exists ( JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php' )) {
				require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
				$uri  = JURI::getInstance();
				$base = $uri->toString( array('scheme', 'host', 'port'));
				$initialize = true;
			} else {
				$initialize = false;
			}
		}
		if ( $initialize === false ) return;

		//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
		if($view == 'category') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getCategoryRoute($slug));
			//$link = $base . JRoute::_( 'index.php?view='.$view.'&cid='.$slug, false );
		} elseif($view == FLEXI_ITEMVIEW) {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($itemslug, $slug, 0, $item));
			//$link = $base . JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug, false );
		} elseif($view == 'tags') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($itemslug));
			//$link = $base . JRoute::_( 'index.php?view='.$view.'&id='.$slug, false );
		} else {
			$link = $base . JRoute::_( 'index.php?view='.$view, false );
		}

		$mail_to_url = JRoute::_('index.php?option=com_mailto&tmpl=component&link='.MailToHelper::addLink($link));$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+(screen.width-100)+\',top=20,height=\'+(screen.height-160)+\',directories=no,location=no';
		$status = 'left=50,width=\'+((screen.width-100) > 800 ? 800 : (screen.width-100))+\',top=20,height=\'+((screen.width-160) > 800 ? 800 : (screen.width-160))+\',menubar=yes,resizable=yes';
		$onclick = ' window.open(this.href,\'win2\',\''.$status.'\'); return false; ';
		
		// This checks template image directory for image, if none found, default image is returned
		$show_icons = $params->get('show_icons');
		if ( $show_icons ) {
			$attribs = '';
			$image = FLEXI_J16GE ?
				JHTML::image(FLEXI_ICONPATH.'emailButton.png', JText::_( 'FLEXI_EMAIL' ), $attribs) :
				JHTML::_('image.site', 'emailButton.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_EMAIL' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib = JText::_( 'FLEXI_EMAIL_TIP' );
		$text = JText::_( 'FLEXI_EMAIL' );
		
		$button_classes = 'fc_mailbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
		} else {
			$caption = $text;
			$button_classes .= FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
		}
		$button_classes .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		// emailed link was set above
		$output	= '<a href="'.$mail_to_url.'" class="'.$button_classes.'" title="'.$tooltip_title.'" onclick="'.$onclick.'" >'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		
		return $output;
	}

	/**
	 * Creates the pdf button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function pdfbutton( $item, &$params)
	{
		if ( FLEXI_J16GE || !$params->get('show_pdf_icon') || JRequest::getCmd('print') ) return;
		
		$show_icons = $params->get('show_icons');
		if ( $show_icons ) {
			$attribs = '';
			$image = FLEXI_J16GE ?
				JHTML::image(FLEXI_ICONPATH.'pdf_button.png', JText::_( 'FLEXI_CREATE_PDF' ), $attribs) :
				JHTML::_('image.site', 'pdf_button.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_CREATE_PDF' ), $attribs);
		} else {
			$image = '';
		}
		
		$overlib = JText::_( 'FLEXI_CREATE_PDF_TIP' );
		$text = JText::_( 'FLEXI_CREATE_PDF' );
		
		$button_classes = 'fc_pdfbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
		} else {
			$caption = $text;
			$button_classes .= FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
		}
		$button_classes .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		$link 	= JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug.'&format=pdf');
		$output	= '<a href="'.$link.'" class="'.$button_classes.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );

		return $output;
	}


	/**
	 * Creates the state selector button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function statebutton( $item, &$params=null, $addToggler=true )
	{
		// Check for empty params too
		if ( $params && !$params->get('show_state_icon', 1) || JRequest::getCmd('print') ) return;
		
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$document = JFactory::getDocument();
		$nullDate = $db->getNullDate();
		$app = JFactory::getApplication();

		// Determine general archive privilege
		static $has_archive = null;
		if ($has_archive === null) {
			$permission  = FlexicontentHelperPerm::getPerm();
			$has_archive = $permission->CanArchives;
		}
		
		// Determine edit state, delete privileges of the current user on the given item
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
			$has_delete     = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $item->created_by == $user->get('id'));
		} else if ($user->gid >= 25) {
			$has_edit_state = true;
			$has_delete     = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit_state = in_array('publish', $rights) || (in_array('publishown', $rights) && $item->created_by == $user->get('id')) ;
			$has_delete     = in_array('delete', $rights) || (in_array('deleteown', $rights) && $item->created_by == $user->get('id')) ;
		} else {
			$has_edit_state = $user->authorize('com_content', 'publish', 'content', 'all');
			$has_delete     = $user->gid >= 23; // is at least manager
		}
		
		// Display state toggler if it can do any of state change
		$canChangeState = $has_edit_state || $has_delete || $has_archive;

		static $js_and_css_added = false;

	 	if (!$js_and_css_added && $canChangeState && $addToggler )
	 	{
			// File exists both in frontend & backend (and is different), so we will use 'base' method and not 'root'
			$document->addScript( JURI::base(true).'/components/com_flexicontent/assets/js/stateselector.js' );
	 		$js ='
				if(MooTools.version>="1.2.4") {
					window.addEvent("domready", function() {stateselector.init()});
				}else{
					window.onDomReady(stateselector.init.bind(stateselector));
				}
				function dostate(state, id)
				{
					var change = new processstate();
					change.dostate( state, id );
				}';
			$document->addScriptDeclaration($js);
			$js_and_css_added = true;
	 	}
	 	
	 	static $state_names = null;
	 	static $state_descrs = null;
	 	static $state_imgs = null;
	 	if ( !$state_names ) {
			$state_names = array(1=>JText::_('FLEXI_PUBLISHED'), -5=>JText::_('FLEXI_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISHED'), -3=>JText::_('FLEXI_PENDING'), -4=>JText::_('FLEXI_TO_WRITE'), (FLEXI_J16GE ? 2:-1)=>JText::_('FLEXI_ARCHIVED'), -2=>JText::_('FLEXI_TRASHED'), ''=>'FLEXI_UNKNOWN');
			$state_descrs = array(1=>JText::_('FLEXI_PUBLISH_THIS_ITEM'), -5=>JText::_('FLEXI_SET_ITEM_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISH_THIS_ITEM'), -3=>JText::_('FLEXI_SET_ITEM_PENDING'), -4=>JText::_('FLEXI_SET_ITEM_TO_WRITE'), (FLEXI_J16GE ? 2:-1)=>JText::_('FLEXI_ARCHIVE_THIS_ITEM'), -2=>JText::_('FLEXI_TRASH_THIS_ITEM'), ''=>'FLEXI_UNKNOWN');
			$state_imgs = array(1=>'tick.png', -5=>'publish_g.png', 0=>'publish_x.png', -3=>'publish_r.png', -4=>'publish_y.png', (FLEXI_J16GE ? 2:-1)=>'archive.png', -2=>'trash.png', ''=>'unknown.png');
		}
		
		// Create state icon
		$state = $item->state;
		$state_text ='';
		$tmpparams = FLEXI_J16GE ? new JRegistry() : new JParameter("");
		$tmpparams->set('stateicon_popup', 'none');
		$stateicon = flexicontent_html::stateicon( $state, $tmpparams, $state_text );


		$tz_string = JFactory::getApplication()->getCfg('offset');
		if (FLEXI_J16GE) {
			$tz = new DateTimeZone( $tz_string );
			$tz_offset = $tz->getOffset(new JDate()) / 3600;
		} else {
			$tz_offset = $tz_string;
		}

	 	// Calculate common variables used to produce output
		$publish_up = JFactory::getDate($item->publish_up);
		$publish_down = JFactory::getDate($item->publish_down);
		if (FLEXI_J16GE) {
			$publish_up->setTimezone($tz);
			$publish_down->setTimezone($tz);
		} else {
			$publish_up->setOffset($tz_offset);
			$publish_down->setOffset($tz_offset);
		}

		$img_path = JURI::root(true)."/components/com_flexicontent/assets/images/";


		// Create publish information
		$publish_info = '';
		if (isset($item->publish_up)) {
			if ($item->publish_up == $nullDate) {
				$publish_info .= JText::_( 'FLEXI_START_ALWAYS' );
			} else {
				$publish_info .= JText::_( 'FLEXI_START' ) .": ". JHTML::_('date', FLEXI_J16GE ? $publish_up->toSql() : $publish_up->toMySQL(), FLEXI_J16GE ? 'Y-m-d H:i:s' : '%Y-%m-%d %H:%M:%S');
			}
		}
		if (isset($item->publish_down)) {
			if ($item->publish_down == $nullDate) {
				$publish_info .= "<br />". JText::_( 'FLEXI_FINISH_NO_EXPIRY' );
			} else {
				$publish_info .= "<br />". JText::_( 'FLEXI_FINISH' ) .": ". JHTML::_('date', FLEXI_J16GE ? $publish_down->toSql() : $publish_down->toMySQL(), FLEXI_J16GE ? 'Y-m-d H:i:s' : '%Y-%m-%d %H:%M:%S');
			}
		}
		$publish_info = $state_text.'<br /><br />'.$publish_info;


		// Create the state selector button and return it
		if ( $canChangeState && $addToggler )
		{
			$separators_at = array(-5,-4);
			// Only add user's permitted states on the current item
			if ($has_edit_state) $state_ids   = array(1, -5, 0, -3, -4);
			if ($has_archive)    $state_ids[] = FLEXI_J16GE ? 2:-1;
			if ($has_delete)     $state_ids[]  = -2;

			$box_css = ''; //$app->isSite() ? 'width:182px; left:-100px;' : '';
			$publish_info .= '<br><br>'.JText::_('FLEXI_CLICK_TO_CHANGE_STATE');
			
			$button_classes = 'fc_statebutton';
			$button_classes .= FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			$button_classes .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$tooltip_title = flexicontent_html::getToolTip(JText::_( 'FLEXI_PUBLISH_INFORMATION' ), $publish_info, 0);
			$output ='
			<ul class="statetoggler">
				<li class="topLevel">
					<a href="javascript:void(0);" style="outline:none;" id="row'.$item->id.'" class="opener '.$button_classes.'" title="'.$tooltip_title.'">
						'.$stateicon.'
					</a>
					<div class="options" style="'.$box_css.'">
						<ul>';
				
				$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
				$title_header = JText::_( 'FLEXI_ACTION' );
				foreach ($state_ids as $i => $state_id) {
					$tooltip_title = flexicontent_html::getToolTip($title_header, $state_descrs[$state_id], 0);
					$spacer = in_array($state_id,$separators_at) ? '' : '';
					$output .='
							<li>
								<a href="javascript:void(0);" onclick="dostate(\''.$state_id.'\', \''.$item->id.'\')" class="closer '.$tooltip_class.'" title="'.$tooltip_title.'">
									<img src="'.$img_path.$state_imgs[$state_id].'" width="16" height="16" style="border-width:0;" alt="'.$state_names[$state_id].'" />
								</a>
							</li>';
				}
				$output .='
						</ul>
					</div>
				</li>
			</ul>';

		} else if ($app->isAdmin()) {
			if ($canChangeState) $publish_info .= '<br><br>'.JText::_('FLEXI_STATE_CHANGER_DISABLED');

			$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$tooltip_title = flexicontent_html::getToolTip(JText::_( 'FLEXI_PUBLISH_INFORMATION' ), $publish_info, 0);
			
			$output = '
				<div id="row'.$item->id.'">
					<span class="'.$tooltip_class.'" title="'.$tooltip_title.'">
						'.$stateicon.'
					</span>
				</div>';
			$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		} else {
			$output = '';  // frontend with no permissions to edit / delete / archive
		}

		return $output;
	}


	/**
	 * Creates the approval button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function approvalbutton( $item, &$params)
	{
		if ( JRequest::getCmd('print') ) return;
		
		static $user = null, $requestApproval = null;
		if ($user === null) {
			$user	= JFactory::getUser();
			$requestApproval = FLEXI_J16GE ? $user->authorise('flexicontent.requestapproval',	'com_flexicontent') : ($user->gid >= 20);
		}

		// Skip items not in draft state
		if ( $item->state != -4 )  return;
		
		// Skip not-owned items, unless having privilege to send approval request for any item
		if ( !$requestApproval && $item->created_by != $user->get('id') )  return;
		
		// Determine if current user can edit state of the given item
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
			// ALTERNATIVE 1
			//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
			//$has_edit_state = in_array('edit.state', $rights) || (in_array('edit.state.own', $rights) && $item->created_by == $user->get('id')) ;
		} else if ($user->gid >= 25) {
			$has_edit_state = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit_state = in_array('publish', $rights) || (in_array('publishown', $rights) && $item->created_by == $user->get('id')) ;
		} else {
			$has_edit_state = $user->authorize('com_content', 'publish', 'content', 'all');
		}

		// Create the approval button only if user cannot edit the item (**note check at top of this method)
		if ( $has_edit_state ) return;
		
		$show_icons = 2; //$params->get('show_icons');
		if ( $show_icons ) {
			$attribs = '';
			$image = FLEXI_J16GE ?
				JHTML::image('components/com_flexicontent/assets/images/'.'key_add.png', JText::_( 'FLEXI_APPROVAL_REQUEST' ), $attribs) :
				JHTML::_('image.site', 'key_add.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_APPROVAL_REQUEST' ), $attribs) ;
		} else {
			$image = '';
		}
		
		$overlib 	= JText::_( 'FLEXI_APPROVAL_REQUEST_INFO' );
		$text 		= JText::_( 'FLEXI_APPROVAL_REQUEST' );
		
		$button_classes = 'fc_approvalbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
		} else {
			$caption = $text;
			$button_classes .= FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
		}
		$button_classes .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		$link = 'index.php?option=com_flexicontent&task=approval&cid='.$item->id;
		$output	= '<a href="'.$link.'" class="'.$button_classes.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		
		return $output;
	}


	/**
	 * Creates the edit button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function editbutton( $item, &$params)
	{
		if ( !$params->get('show_editbutton', 1) || JRequest::getCmd('print') ) return;
		
		$user	= JFactory::getUser();
		
		// Determine if current user can edit the given item
		$has_edit_state = false;
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit_state = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $item->created_by == $user->get('id'));
			// ALTERNATIVE 1
			//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
			//$has_edit_state = in_array('edit', $rights) || (in_array('edit.own', $rights) && $item->created_by == $user->get('id')) ;
		} else if ($user->gid >= 25) {
			$has_edit_state = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit_state = in_array('edit', $rights) || (in_array('editown', $rights) && $item->created_by == $user->get('id')) ;
		} else {
			$has_edit_state = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $item->created_by == $user->get('id'));
		}

		// Create the edit button only if user can edit the give item
		if ( !$has_edit_state ) return;
		
		$show_icons = $params->get('show_icons');
		if ( $show_icons ) {
			$attribs = '';
			$image = FLEXI_J16GE ?
				JHTML::image(FLEXI_ICONPATH.'edit.png', JText::_( 'FLEXI_EDIT' ), $attribs) :
				JHTML::_('image.site', 'edit.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_EDIT' ), $attribs) ;
		} else {
			$image = '';
		}
		
		$overlib 	= JText::_( 'FLEXI_EDIT_TIP' );
		$text 		= JText::_( 'FLEXI_EDIT' );
		
		$button_classes = 'fc_editbutton';
		if ( $show_icons==1 ) {
			$caption = '';
			$button_classes .= '';
		} else {
			$caption = $text;
			$button_classes .= FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
		}
		$button_classes .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		// Maintain menu item ? e.g. current category view, 
		$Itemid = JRequest::getInt('Itemid', 0);  //$Itemid = 0;
		$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, $Itemid, $item));
		$link = $item_url  .(strstr($item_url, '?') ? '&' : '?').  'task=edit';
		$output	= '<a href="'.$link.'" class="'.$button_classes.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		
		return $output;
	}

	/**
	 * Creates the add button
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function addbutton(&$params, &$submit_cat = null, $menu_itemid = 0, $submit_text = '', $auto_relations = false, $ignore_unauthorized = false)
	{
		if ( !$params->get('show_addbutton', 1) || JRequest::getCmd('print') ) return;
		
		// Currently add button will appear to logged users only
		// ... unless unauthorized users are allowed
		$user	= JFactory::getUser();
		if ( !$user->id && $ignore_unauthorized < 2 ) return '';
		
		
		// IF not auto-relation given ... then check if current view / layout can use ADD button
		$view = JRequest::getVar('view');
		$layout = JRequest::getVar('layout', 'default');
		if ( !$auto_relations ) {
			if ( $view!='category' || $layout == 'author' ) return '';
		}
		
		
		// *********************************************************************
		// Check if user can ADD to (a) given category or to (b) at any category
		// *********************************************************************
		
		// (a) Given category
		if ( $submit_cat && $submit_cat->id )
		{
			if (FLEXI_J16GE) {
				$canAdd = $user->authorise('core.create', 'com_content.category.' . $submit_cat->id);
			} else if (FLEXI_ACCESS) {
				$canAdd = ($user->gid < 25) ? FAccess::checkAllContentAccess('com_content','submit','users', $user->gmid, 'category', $submit_cat->id) : 1;
			} else {
				$canAdd	= $user->authorize('com_content', 'add', 'content', 'all');
				//$canAdd = ($user->gid >= 19);  // At least J1.5 Author
			}
		}
		
		// (b) Any category (or to the CATEGORY IDS of given CATEGORY VIEW OBJECT)
		else
		{
			// Given CATEGORY VIEW OBJECT may limit to specific category ids
			if (FLEXI_J16GE) {
				$canAdd = $user->authorise('core.create', 'com_flexicontent');
			} else if ($user->gid >= 25) {
				$canAdd = 1;
			} else if (FLEXI_ACCESS) {
				$canAdd = FAccess::checkUserElementsAccess($user->gmid, 'submit');
				$canAdd = @$canAdd['content'] || @$canAdd['category'];
			} else {
				$canAdd	= $user->authorize('com_content', 'add', 'content', 'all');
			}
			
			if ($canAdd === NULL && $user->id) {
				// Perfomance concern (NULL for $canAdd) means SOFT DENY, also check for logged user
				// thus to avoid checking some/ALL categories for "create" privelege for unlogged users
				$specific_catids = $submit_cat ? @ $submit_cat->ids  :  false;
				if ($specific_catids && count($specific_catids) > 3) $specific_catids = false;
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.create'), $require_all=true, $check_published = true, $specific_catids, $find_first = true );
				$canAdd = count($allowedcats);
			}
		}
		
		if ( !$canAdd && !$ignore_unauthorized ) return '';
		
		
		// ******************************
		// Create submit button/icon text
		// ******************************
		
		if ($submit_text) {
			$submit_lbl = JText::_($submit_text);
		} else {
			$submit_lbl = JText::_( $submit_cat && $submit_cat->id  ?  'FLEXI_ADD_NEW_CONTENT_TO_CURR_CAT'  :  'FLEXI_ADD_NEW_CONTENT_TO_LIST' );
		}		
		
		
		// ***********
		// Create link
		// ***********
		
		// Add Itemid (if given) and do SEF URL routing it --before-- appending more variables, so that
		// ... menu item URL variables from given menu item ID will be appended if SEF URLs are OFF
		$menu_itemid = $menu_itemid ? $menu_itemid : (int)$params->get('addbutton_menu_itemid', 0);
		$link  = 'index.php?option=com_flexicontent';
		$link .= $menu_itemid  ? '&Itemid='.$menu_itemid  :  '&view='.FLEXI_ITEMVIEW.'&task=add';
		$link  = JRoute::_($link);
		
		// Add main category ID (if given)
		$link .= ($submit_cat && $submit_cat->id)  ?  '&maincat='.$submit_cat->id  :  '';
		
		// Append autorelate information to the URL (if given)
		if ($auto_relations) foreach ( $auto_relations as $auto_relation ) {
			$link .= (strstr($link, '?') ? '&' : '?') . 'autorelation_'.$auto_relation->fieldid.'='.$auto_relation->itemid;
		}
		
		
		// ***************************************
		// Finally create the submit icon / button
		// ***************************************
		
		$overlib = $submit_lbl;
		$text = JText::_( 'FLEXI_ADD' );
		
		$show_icons = 2; //$params->get('show_icons');
		if ( $show_icons && !$auto_relations ) {
			$attribs = '';
			$image = FLEXI_J16GE ?
				JHTML::image('components/com_flexicontent/assets/images/'.'plus-button.png', $submit_lbl, $attribs) :
				JHTML::_('image.site', 'plus-button.png', 'components/com_flexicontent/assets/images/', NULL, NULL, $submit_lbl, $attribs) ;
		} else {
			$image = '';
		}
		
		$button_classes = 'fc_addbutton';
		if ( $show_icons==1 && !$auto_relations ) {
			$caption = '';
			$button_classes .= '';
		} else {
			$caption = $text;
			$button_classes .=
				(FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall')
				.($auto_relations ? ' btn-success' : '');
		}
		$button_classes .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$tooltip_title = flexicontent_html::getToolTip($text, $overlib, 0);
		
		$output	= '<a href="'.$link.'" class="'.$button_classes.'" title="'.$tooltip_title.'">'.$image.$caption.'</a>';
		if (!$auto_relations) {
			$output	= JText::_( 'FLEXI_ICON_SEP' ) .$output. JText::_( 'FLEXI_ICON_SEP' );
		}
		
		return $output;
	}

	/**
	 * Creates the stateicon
	 *
	 * @param int $state
	 * @param array $params
	 * @since 1.0
	 */
	static function stateicon( $state, &$params, &$state_text=null )
	{
	 	static $state_names = null;
	 	static $state_descrs = null;
	 	static $state_imgs = null;
	 	static $state_basictips = null;
	 	static $state_fulltips = null;
	 	if ( !$state_names ) {
			$state_names = array(1=>JText::_('FLEXI_PUBLISHED'), -5=>JText::_('FLEXI_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISHED'), -3=>JText::_('FLEXI_PENDING'), -4=>JText::_('FLEXI_TO_WRITE'), (FLEXI_J16GE ? 2:-1)=>JText::_('FLEXI_ARCHIVED'), -2=>JText::_('FLEXI_TRASHED'), ''=>'FLEXI_UNKNOWN');
			$state_descrs = array(1=>JText::_('FLEXI_PUBLISH_THIS_ITEM'), -5=>JText::_('FLEXI_SET_ITEM_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISH_THIS_ITEM'), -3=>JText::_('FLEXI_SET_ITEM_PENDING'), -4=>JText::_('FLEXI_SET_ITEM_TO_WRITE'), (FLEXI_J16GE ? 2:-1)=>JText::_('FLEXI_ARCHIVE_THIS_ITEM'), -2=>JText::_('FLEXI_TRASH_THIS_ITEM'), ''=>'FLEXI_UNKNOWN');
			$state_imgs = array(1=>'tick.png', -5=>'publish_g.png', 0=>'publish_x.png', -3=>'publish_r.png', -4=>'publish_y.png', (FLEXI_J16GE ? 2:-1)=>'archive.png', -2=>'trash.png', ''=>'unknown.png');
		}
		
	 	if ( !$state_fulltips ) {
			$title = JText::_( 'FLEXI_STATE' );
			foreach($state_names as $state_id => $state_name) {
				$content = str_replace('::', '-', $state_name);
				$state_fulltips[$state_id] = flexicontent_html::getToolTip($title, $content, 0);
			}
		}
		
	 	if ( !$state_basictips ) {
			foreach($state_names as $state_id => $state_name) {
				$content = !FLEXI_J30GE ? str_replace('::', '-', $state_name) : $state_name;
				$state_basictips[$state_id] = $title.' : '.$content;
			}
		}
		
		// Check for invalid state
		if ( !isset($state_names[$state]) ) $state = '';
		
		// Create popup text
		switch ( $params->get('stateicon_popup', 'full') )
		{
			case 'basic':
				$attribs = 'title="'.$state_basictips[$state].'"';
				break;
			case 'none':
				$attribs = '';
				break;
			case 'full': default:
				$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
				$attribs = 'class="fc_stateicon '.$tooltip_class.'" title="'.$state_fulltips[$state].'"';
				break;
		}
		
		// Create state icon image
		$app = JFactory::getApplication();
		$path = (!FLEXI_J16GE && $app->isAdmin() ? '../' : '').'components/com_flexicontent/assets/images/';
		if ( $params->get('show_icons', 1) ) {
			$img = $state_imgs[$state];
			$icon = FLEXI_J16GE ?
				JHTML::image($path.$img, $state_names[$state], $attribs) :
				JHTML::_('image.site', $img, $path, NULL, NULL, $state_names[$state], $attribs) ;
		} else {
			$icon = $state_names[$state];
		}
		
		return $icon;
	}


	/**
	 * Creates the ratingbar
	 *
	 * @deprecated
	 * @param array $item
	 * @since 1.0
	 */
	static function ratingbar($item)
	{
		//sql calculation doesn't work with negative values and thus only minus votes will not be taken into account
		if ($item->votes == 0) {
			return JText::_( 'FLEXI_NOT_YET_RATED' );
		}

		//we do the rounding here and not in the query to get better ordering results
		$rating = round($item->votes);

		$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		$tooltip_title = flexicontent_html::getToolTip(JText::_('FLEXI_RATING'), JText::_( 'FLEXI_SCORE' ).': '.$rating.'%', 0, 1);
		$output = '<span class="qf_ratingbarcontainer'.$tooltip_class.'" title="'.$tooltip_title.'">';
		$output .= '<span class="qf_ratingbar" style="width:'.$rating.'%;">&nbsp;</span></span>';

		return $output;
	}

	/**
	 * Creates the voteicons
	 * Deprecated to ajax votes
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function voteicons($item, &$params)
	{
		static $voteup, $votedown, $tooltip_class, $tip_vote_up, $tip_vote_down;
		if (!$tooltip_class)
		{
			$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$show_icons = $params->get('show_icons');
			if ( $show_icons ) {
				$voteup = FLEXI_J16GE ?
					JHTML::image('components/com_flexicontent/assets/images/'.'thumb_up.png', JText::_( 'FLEXI_GOOD' ), NULL) :
					JHTML::_('image.site', 'thumb_up.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_GOOD' ) ) ;
				$votedown = FLEXI_J16GE ?
					JHTML::image('components/com_flexicontent/assets/images/'.'thumb_down.png', JText::_( 'FLEXI_BAD' ), NULL) :
					JHTML::_('image.site', 'thumb_down.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_BAD' ) ) ;
			} else {
				$voteup = JText::_( 'FLEXI_GOOD' ). '&nbsp;';
				$votedown = '&nbsp;'.JText::_( 'FLEXI_BAD' );
			}
			$tip_vote_up = flexicontent_html::getToolTip('FLEXI_VOTE_UP', 'FLEXI_VOTE_UP_TIP', 1, 1);
			$tip_vote_down = flexicontent_html::getToolTip('FLEXI_VOTE_DOWN', 'FLEXI_VOTE_DOWN_TIP', 1, 1);
		}
		
		$item_url = JRoute::_('index.php?task=vote&vote=1&cid='.$item->categoryslug.'&id='.$item->slug.'&layout='.$params->get('ilayout'));
		$link = $item_url .(strstr($item_url, '?') ? '&' : '?');
		$output = '<a href="'.$link.'vote=1" class="fc_vote_up'.$tooltip_class.'" title="'.$tip_vote_up.'">'.$voteup.'</a>';
		$output .= ' - ';
		$output .= '<a href="'.$link.'vote=1" class="fc_vote_down'.$tooltip_class.'" title="'.$tip_vote_down.'">'.$votedown.'</a>';
		
		return $output;
	}

	/**
	 * Creates the ajax voting stars system
	 *
	 * @param array $field
	 * @param int or string $xid
	 * @since 1.0
	 */
	static function ItemVote( &$field, $xid, $vote )
	{
		// Check for invalid xid
		if ($xid!='main' && $xid!='extra' && $xid!='all' && !(int)$xid) {
			$html .= "ItemVote(): invalid xid '".$xid."' was given";
			return;
		}

		$db	= JFactory::getDBO();
  	$id  = $field->item_id;

  	$enable_extra_votes = $field->parameters->get('extra_votes', '');
		$extra_votes = !$enable_extra_votes ? '' : $field->parameters->get('extra_votes', '');
		$main_label  = !$enable_extra_votes ? '' : $field->parameters->get('main_label', '');
		// Set a Default main label if one was not given but extra votes exist
		$main_label  = (!$main_label && $extra_votes) ? JText::_('FLEXI_OVERALL') : $main_label;

		$html = '';

		if (!$vote) {
			// These are mass retrieved for multiple items, to optimize performance
			//$db->setQuery( 'SELECT * FROM #__content_rating WHERE content_id=' . $id );
			//$vote = $db->loadObject();
			$vote = new stdClass();
			$vote->rating_sum = $vote->rating_count = 0;
		} else if (!isset($vote->rating_sum) || !isset($vote->rating_sum)) {
			$vote->rating_sum = $vote->rating_count = 0;
		}

		if ($xid=='main' || $xid=='all') {
			$html .= flexicontent_html::ItemVoteDisplay( $field, $id, $vote->rating_sum, $vote->rating_count, 'main', $main_label );
		}

		if ($xid=='all' || $xid=='extra' || (int)$xid) {

			// Retrieve and split-up extra vote types, (removing last one if empty)
			$extra_votes = preg_split("/[\s]*%%[\s]*/", $extra_votes);
			if ( empty($extra_votes[count($extra_votes)-1]) )  unset( $extra_votes[count($extra_votes)-1] );

			// Split extra voting ids (xid) and their titles
			$xid_arr = array();
			foreach ($extra_votes as $extra_vote) {
				list($extra_id, $extra_title) = explode("##", $extra_vote);
				$xid_arr[$extra_id] = $extra_title;
			}

			// Query the database
			if ( (int)$xid )
			{
				if ( !isset($vote->extra[(int)$xid]) ) {
					$extra_vote = new stdClass();
					$extra_vote->rating_sum = $extra_vote->rating_count = 0;
					$extra_vote->extra_id = (int)$xid;
				} else {
					$extra_vote = $vote->extra[(int)$xid];
				}
				$html .= flexicontent_html::ItemVoteDisplay( $field, $id, $extra_vote->rating_sum, $extra_vote->rating_count, $extra_vote->extra_id, $xid_arr[(int)$xid] );
			}
			else
			{
				foreach ( $xid_arr as $extra_id => $extra_title) {
					if ( !isset($vote->extra[$extra_id]) ) {
						$extra_vote = new stdClass();
						$extra_vote->rating_sum = $extra_vote->rating_count = 0;
						$extra_vote->extra_id = $extra_id;
					} else {
						$extra_vote = $vote->extra[$extra_id];
					}
					$html .= flexicontent_html::ItemVoteDisplay( $field, $id, $extra_vote->rating_sum, $extra_vote->rating_count, $extra_vote->extra_id, $extra_title );
				}
			}
		}

		return $html;
 	}

	/**
	 * Method that creates the stars
	 *
	 * @param array				$field
	 * @param int 				$id
	 * @param int			 	$rating_sum
	 * @param int 				$rating_count
	 * @param int or string 	$xid
	 * @since 1.0
	 */
 	static function ItemVoteDisplay( &$field, $id, $rating_sum, $rating_count, $xid, $label='', $stars_override=0, $allow_vote=true, $vote_counter='default', $show_counter_label=true )
	{
		static $acclvl_names  = null;
		static $star_tooltips = null;
		static $star_classes  = null;
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		
		
		// *****************************************************
		// Find if user has the ACCESS level required for voting
		// *****************************************************
		
		if (!FLEXI_J16GE) $aid = (int) $user->get('aid');
		else $aid_arr = JAccess::getAuthorisedViewLevels($user->id);
		$acclvl = (int) $field->parameters->get('submit_acclvl', FLEXI_J16GE ? 1 : 0);
		$has_acclvl = FLEXI_J16GE ? in_array($acclvl, $aid_arr) : $acclvl <= $aid;
		
		
		// *********************************************************
		// Calculate NO access actions, (case that user cannot vote)
		// *********************************************************
		
		if ( !$has_acclvl )
		{
			if ($user->id) {
				$no_acc_msg = $field->parameters->get('logged_no_acc_msg', '');
				$no_acc_url = $field->parameters->get('logged_no_acc_url', '');
				$no_acc_doredirect  = $field->parameters->get('logged_no_acc_doredirect', 0);
				$no_acc_askredirect = $field->parameters->get('logged_no_acc_askredirect', 1);
			} else {
				$no_acc_msg  = $field->parameters->get('guest_no_acc_msg', '');
				$no_acc_url  = $field->parameters->get('guest_no_acc_url', '');
				$no_acc_doredirect  = $field->parameters->get('guest_no_acc_doredirect', 2);
				$no_acc_askredirect = $field->parameters->get('guest_no_acc_askredirect', 1);
			}
			
			// Decide no access Redirect URLs
			if ($no_acc_doredirect == 2) {
				$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
				$no_acc_url = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
			} else if ($no_acc_doredirect == 0) {
				$no_acc_url = '';
			} // else unchanged
			
			
			// Decide no access Redirect Message
			$no_acc_msg = $no_acc_msg ? JText::_($no_acc_msg) : '';
			if ( !$no_acc_msg )
			{
				// Find name of required Access Level
				if (FLEXI_J16GE) {
					$acclvl_name = '';
					if ($acclvl && empty($acclvl_names)) {  // Retrieve this ONCE (static var)
						$db->setQuery('SELECT title,id FROM #__viewlevels as level');
						$_lvls = $db->loadObjectList();
						$acclvl_names = array();
						if (!empty($_lvls)) foreach ($_lvls as $_lvl) $acclvl_names[$_lvl->id] = $_lvl->title;
					}
				} else {
					$acclvl_names = array(0=>'Public', 1=>'Registered', 2=>'Special');
					$acclvl_name = $acclvl_names[$acclvl];
				}
				$acclvl_name =  !empty($acclvl_names[$acclvl]) ? $acclvl_names[$acclvl] : "Access Level: ".$acclvl." not found/was deleted";
				$no_acc_msg = JText::sprintf( 'FLEXI_NO_ACCESS_TO_VOTE' , $acclvl_name);
			}
			$no_acc_msg_redirect = JText::_($no_acc_doredirect==2 ? 'FLEXI_CONFIM_REDIRECT_TO_LOGIN_REGISTER' : 'FLEXI_CONFIM_REDIRECT');
		}
		
		$counter 	= $field->parameters->get( 'counter', 1 );   // 0: disable showing vote counter, 1: enable for main and for extra votes
		if ($vote_counter != 'default' ) $counter = $vote_counter ? 1 : 0;
		$unrated 	= $field->parameters->get( 'unrated', 1 );
		$dim			= $field->parameters->get( 'dimension', 16 );
		$image		= $field->parameters->get( 'image', 'components/com_flexicontent/assets/images/star-small.png' );
		$class 		= $field->name;
		$img_path	= JURI::root(true).'/'.$image;
		
		// Get number of displayed stars, configuration
		$rating_resolution = (int)$field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5   ?  $rating_resolution  :  5;
		$rating_resolution = $rating_resolution <= 100  ?  $rating_resolution  :  100;
		
		// Get number of displayed stars, configuration
		$rating_stars = (int) ($stars_override ? $stars_override : $field->parameters->get('rating_stars', 5));
		$rating_stars = $rating_stars > $rating_resolution ? $rating_resolution  :  $rating_stars;  // Limit stars to resolution
		
		static $js_and_css_added = false;

	 	if (!$js_and_css_added)
	 	{
			// Make sure mootools are loaded before our js
			FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
			
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			if ($cparams->get('add_tooltips', 1))
			{
				// Load J2.5 (non-bootstrap tooltips) tooltips, we still need regardless of using J3.x, since some code may still use them
				JHTML::_('behavior.tooltip');
				
				// J3.0+ tooltips (bootstrap based)
				if (FLEXI_J30GE) JHtml::_('bootstrap.tooltip');
			}
			
			$document = JFactory::getDocument();
			$css 	= JURI::root(true).'/components/com_flexicontent/assets/css/fcvote.css';
			$js		= JURI::root(true).'/components/com_flexicontent/assets/js/fcvote.js';
			$document->addStyleSheet($css);
			$document->addScript($js);

			$document->addScriptDeclaration('var fcvote_rfolder = "'.JURI::root(true).'";');

			$css = '
			.'.$class.' .fcvote {line-height:'.$dim.'px;}
			.'.$class.' .fcvote-label {margin-right: 6px;}
			.'.$class.' .fcvote ul {height:'.$dim.'px; position:relative !important; left:0px !important;}
			.'.$class.' .fcvote ul, .'.$class.' .fcvote ul li a:hover, .'.$class.' .fcvote ul li.current-rating {background-image:url('.$img_path.')!important;}
			.'.$class.' .fcvote ul li a, .'.$class.' .fcvote ul li.current-rating {height:'.$dim.'px;line-height:'.$dim.'px;}
			';
			
			$star_tooltips = array();
			$star_classes  = array();
			for ($i=1; $i<=$rating_resolution; $i++) {
				$star_zindex  = $rating_resolution - $i + 2;
				$star_percent = (int) round(100 * ($i / $rating_resolution));
				$css .= '.fcvote li a.star'.$i.' { width: '.$star_percent.'%; z-index: '.$star_zindex.'; }' ."\n";
				$star_classes[$i] = 'star'.$i;
				if ($star_percent < 20)       $star_tooltips[$i] = JText::_( 'FLEXI_VERY_POOR' );
				else if ($star_percent < 40)  $star_tooltips[$i] = JText::_( 'FLEXI_POOR' );
				else if ($star_percent < 60)  $star_tooltips[$i] = JText::_( 'FLEXI_REGULAR' );
				else if ($star_percent < 80)  $star_tooltips[$i] = JText::_( 'FLEXI_GOOD' );
				else                          $star_tooltips[$i] = JText::_( 'FLEXI_VERY_GOOD' );
				$star_tooltips[$i] .= ' '.$i.'/'.$rating_resolution;
			}
			
			$document->addStyleDeclaration($css);
			$js_and_css_added = true;
	 	}
	 	
	 	$percent = 0;
	 	$factor = (int) round(100/$rating_resolution);
		if ($rating_count != 0) {
			$percent = number_format((intval($rating_sum) / intval( $rating_count ))*$factor,2);
		} elseif ($unrated == 0) {
			$counter = -1;
		}

		if ( (int)$xid ) {
			// Disable showing vote counter in extra votes
			if ( $counter == 2 ) $counter = 0;
		} else {
			// Disable showing vote counter in main vote
			if ( $counter == 3 ) $counter = 0;
		}
		$nocursor = !$allow_vote ? 'cursor:auto;' : '';
		
		if ($allow_vote)
		{
			// HAS Voting ACCESS
			if ( $has_acclvl ) {
				$href = 'javascript:;';
				$onclick = '';
			}
			// NO Voting ACCESS
			else {
				// WITHOUT Redirection
				if ( !$no_acc_url ) {
					$href = 'javascript:;';
					$popup_msg = addcslashes($no_acc_msg, "'");
					$onclick = 'alert(\''.$popup_msg.'\');';
				}
				// WITH Redirection
				else {
					$href = $no_acc_url;
					$popup_msg = addcslashes($no_acc_msg . ' ... ' . $no_acc_msg_redirect, "'");
					
					if ($no_acc_askredirect==2)       $onclick = 'return confirm(\''.$popup_msg.'\');';
					else if ($no_acc_askredirect==1)  $onclick = 'alert(\''.$popup_msg.'\'); return true;';
					else                              $onclick = 'return true;';
				}
			}
			
			$dovote_class = $has_acclvl ? 'fc_dovote' : '';
			$html_vote_links = '';
			for ($i=1; $i<=$rating_resolution; $i++) {
				$html_vote_links .= '
					<li><a onclick="'.$onclick.'" href="'.$href.'" title="'.$star_tooltips[$i].'" class="'.$dovote_class.' '.$star_classes[$i].'" rel="'.$id.'_'.$xid.'">'.$i.'</a></li>';
			}
		}
		
		$element_width = $rating_resolution * $dim;
		if ($rating_stars) $element_width = (int) $element_width * ($rating_stars / $rating_resolution);
	 	$html='
		<div class="'.$class.'">
			<div class="fcvote">'
	  		.($label ? '<div id="fcvote_lbl'.$id.'_'.$xid.'" class="fcvote-label xid-'.$xid.'">'.$label.'</div>' : '')
				.'<ul style="width:'.$element_width.'px;">
    				<li id="rating_'.$id.'_'.$xid.'" class="current-rating" style="width:'.(int)$percent.'%;'.$nocursor.'"></li>'
    		.@ $html_vote_links
				.'
				</ul>
	  		<div id="fcvote_cnt_'.$id.'_'.$xid.'" class="fcvote-count">';
		  		if ( $counter != -1 ) {
	  				if ( $counter != 0 ) {
							$html .= "(";
							$html .= $rating_count ? $rating_count : "0";
							if ($show_counter_label) $html .= " ".JText::_( $rating_count!=1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE' );
		 	 				$html .= ")";
						}
					}
	 	 	$html .='
	 	 		</div>
 	 			<div class="clear"></div>
 	 		</div>
 	 	</div>';

	 	return $html;
 	}

	/**
	 * Creates the favourited by user list
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function favoured_userlist( &$field, &$item,  $favourites)
	{
		$userlisttype = $field->parameters->get('display_favoured_userlist', 0);
		$maxusercount = $field->parameters->get('display_favoured_max', 12);

		$favuserlist = $favourites ? '('.$favourites.' '.JText::_('FLEXI_USERS') : '';

		if ( !$userlisttype ) return $favuserlist ? $favuserlist.')' : '';
		else if ($userlisttype==1) $uname="u.username";
		else /*if ($userlisttype==2)*/ $uname="u.name";

		$db	= JFactory::getDBO();
		$query = "SELECT $uname FROM #__flexicontent_favourites as ff"
			." LEFT JOIN #__users AS u ON u.id=ff.userid "
			." WHERE ff.itemid=" . $item->id;
		$db->setQuery($query);
		$favusers = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
		if (!is_array($favusers) || !count($favusers)) return $favuserlist ? $favuserlist.']' : '';

		$seperator = ': ';
		$count = 0;
		foreach($favusers as $favuser) {
			$favuserlist .= $seperator . $favuser;
			$seperator = ',';
			$count++;
			if ($count >= $maxusercount) break;
		}
		if (count($favusers) > $maxusercount) $favuserlist .=" ...";
		if (!empty($favuserlist)) $favuserlist .=")";
		return $favuserlist;
	}

 	/**
	 * Creates the favourite icons
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function favicon($field, $favoured, & $item=false)
	{
		$user = JFactory::getUser();

		static $js_and_css_added = false;
		static $tooltip_class, $addremove_tip, $img_fav_add, $img_fav_delete;

	 	if (!$js_and_css_added)
	 	{
			$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$text 		= $user->id ? 'FLEXI_ADDREMOVE_FAVOURITE' : 'FLEXI_FAVOURE';
			$overlib 	= $user->id ? 'FLEXI_ADDREMOVE_FAVOURITE_TIP' : 'FLEXI_FAVOURE_LOGIN_TIP';
			$addremove_tip = flexicontent_html::getToolTip($text, $overlib, 1, 1);
	 		
			// Make sure mootools are loaded before our js
			FLEXI_J30GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
			
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			if ($cparams->get('add_tooltips', 1))
			{
				// Load J2.5 (non-bootstrap tooltips) tooltips, we still need regardless of using J3.x, since some code may still use them
				JHTML::_('behavior.tooltip');
				
				// J3.0+ tooltips (bootstrap based)
				if (FLEXI_J30GE) JHtml::_('bootstrap.tooltip');
			}
			
			$document	= JFactory::getDocument();
			$document->addScript( JURI::root(true).'/components/com_flexicontent/assets/js/fcfav.js' );
			
			$js = "
				var fcfav_rfolder = '".JURI::root(true)."';
				var fcfav_text=Array(
					'".JText::_( 'FLEXI_YOUR_BROWSER_DOES_NOT_SUPPORT_AJAX',true )."',
					'".JText::_( 'FLEXI_LOADING',true )."',
					'".JText::_( 'FLEXI_ADDED_TO_YOUR_FAVOURITES',true )."',
					'".JText::_( 'FLEXI_YOU_NEED_TO_LOGIN',true )."',
					'".JText::_( 'FLEXI_REMOVED_FROM_YOUR_FAVOURITES',true )."',
					'".JText::_( 'FLEXI_USERS',true )."',
					'".JText::_( 'FLEXI_FAVOURE',true )."',
					'".JText::_( 'FLEXI_REMOVE_FAVOURITE',true )."'
					);
				";
			$document->addScriptDeclaration($js);

			$js_and_css_added = true;
		}

		$output = "";

		if ($user->id && $favoured)
		{
			$alt_text = JText::_( 'FLEXI_REMOVE_FAVOURITE' );
			if (!$img_fav_delete) {
				$img_fav_delete = FLEXI_J16GE ?
					JHTML::image('components/com_flexicontent/assets/images/'.'heart_delete.png', $alt_text, NULL) :
					JHTML::_('image.site', 'heart_delete.png', 'components/com_flexicontent/assets/images/', NULL, NULL, $alt_text) ;
			}
			$onclick 	= 'javascript:FCFav('.$field->item_id.');';
			$link 		= 'javascript:void(null)';

			$output		.=
				 '<span class="fcfav_delete">'
				.' <a id="favlink'.$field->item_id.'" href="'.$link.'" onclick="'.$onclick.'" class="fcfav-reponse'.$tooltip_class.'" title="'.$addremove_tip.'">'.$img_fav_delete.'</a>'
				.' <span class="fav_item_id" style="display:none;">'.$item->id.'</span>'
				.' <span class="fav_item_title" style="display:none;">'.$item->title.'</span>'
				.'</span>';

		}
		elseif($user->id)
		{
			$alt_text = JText::_( 'FLEXI_FAVOURE' );
			if (!$img_fav_add) {
				$img_fav_add = FLEXI_J16GE ?
					JHTML::image('components/com_flexicontent/assets/images/'.'heart_add.png', $alt_text, NULL) :
					JHTML::_('image.site', 'heart_add.png', 'components/com_flexicontent/assets/images/', NULL, NULL, $alt_text) ;
			}
			$onclick 	= 'javascript:FCFav('.$field->item_id.');';
			$link 		= 'javascript:void(null)';

			$output		.=
				 '<span class="fcfav_add">'
				.' <a id="favlink'.$field->item_id.'" href="'.$link.'" onclick="'.$onclick.'" class="fcfav-reponse'.$tooltip_class.'" title="'.$addremove_tip.'">'.$img_fav_add.'</a>'
				.' <span class="fav_item_id" style="display:none;">'.$item->id.'</span>'
				.' <span class="fav_item_title" style="display:none;">'.$item->title.'</span>'
				.'</span>';
		}
		else
		{
			$attribs = 'class="'.$tooltip_class.'" title="'.$addremove_tip.'"';
			$image = FLEXI_J16GE ?
				JHTML::image('components/com_flexicontent/assets/images/'.'heart_login.png', JText::_( 'FLEXI_FAVOURE' ), $attribs) :
				JHTML::_('image.site', 'heart_login.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_FAVOURE' ), $attribs) ;

			$output		= $image;
		}

		return $output;
	}
	
	
	/**
	 * Method to build a list of radio or checkbox buttons
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildradiochecklist($options, $name, $selected, $buildtype=0, $attribs = '', $tagid='')
	{
		$selected = is_array($selected) ? $selected : array($selected);
		$tagid = $tagid ? $tagid : $name;
		$n = 0;
		$html = $buildtype==1 || $buildtype==3 ? '<fieldset class="radio btn-group btn-group-yesno">' : '';
		$attribs = $buildtype==1 || $buildtype==3  ? ' class="btn" '.$attribs : $attribs;
		foreach ($options as $value => $text) {
			$tagid_n = $tagid.$n;
			$html .='
			<input type="'.($buildtype > 1 ? 'checkbox' : 'radio').'" class="inputbox" '.(in_array($value, $selected) ? ' checked="checked" ' : '').' value="'.$value.'" id="'.$tagid_n.'" name="'.$name.'" />
			<label id="'.$tagid_n.'-lbl" for="'.$tagid_n.'" '.$attribs.'>'.$text.'</label>
			';
			$n++;
		}
		$html .= $buildtype==1 ? '</fieldset>' : '';
		return $html;
	}
	
	
	/**
	 * Method to build the list for types when performing an edit action
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildtypesselect($types, $name, $selected, $top, $class = 'class="inputbox"', $tagid='', $check_perms=false)
	{
		$user = JFactory::getUser();
		
		$typelist = array();
		if (!is_numeric($top) && is_string($top)) $typelist[] = JHTML::_( 'select.option', '', $top );
		else if ($top) $typelist[] = JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_TYPE' ) );
		
		foreach ($types as $type)
		{
			$allowed = 1;
			if ($check_perms)
			{
				if (FLEXI_J16GE)
					$allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);
				else if (FLEXI_ACCESS && $user->gid < 25)
					$allowed = ! $type->itemscreatable || FAccess::checkAllContentAccess('com_content','submit','users', $user->gmid, 'type', $type->id);
				else
					$allowed = 1;
			}
			
			if ( !$allowed && $type->itemscreatable == 1 ) continue;
			
			if ( !$allowed && $type->itemscreatable == 2 )
				$typelist[] = JHTML::_( 'select.option', $type->id, $type->name, 'value', 'text', $disabled = true );
			else
				$typelist[] = JHTML::_( 'select.option', $type->id, $type->name);
		}
		
		return JHTML::_('select.genericlist', $typelist, $name, $class, 'value', 'text', $selected, $tagid );
	}
	
	
	/**
	 * Method to build the list of the autors
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildauthorsselect($list, $name, $selected, $top, $attribs = 'class="inputbox"')
	{
		$typelist 	= array();

		if (!is_numeric($top) && is_string($top)) $typelist[] = JHTML::_( 'select.option', '', $top );
		else if ($top) $typelist[] 	= JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_AUTHOR' ) );
		
		foreach ($list as $item) {
			$typelist[] = JHTML::_( 'select.option', $item->id, $item->name);
		}
		return JHTML::_('select.genericlist', $typelist, $name, $attribs, 'value', 'text', $selected );
	}


	/**
	 * Method to build the list for types when performing an edit action
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildfieldtypeslist($name, $class, $selected, $group=false, $attribs = 'class="inputbox"')
	{
		$field_types = flexicontent_db::getfieldtypes($group);
		if (!$group) {
			// This should not be neccessary as, it was already done in DB query above
			foreach($field_types as $field_type) {
				$field_type->text = preg_replace("/FLEXIcontent[ \t]*-[ \t]*/i", "", $field_type->text);
				$field_arr[$field_type->text] = $field_type;
			}
			ksort( $field_arr, SORT_STRING );
			
			$list = JHTML::_('select.genericlist', $field_arr, $name, $class, 'value', 'text', $selected );
		} else {
			$fftype = array();
			foreach ($field_types as $field_group => $ft_types) {
				$fftype[] = JHTML::_('select.optgroup', $field_group );
				foreach ($ft_types as $field_type => $ftdata) {
					$field_friendlyname = preg_replace("/FLEXIcontent[ \t]*-[ \t]*/i", "", $ftdata->text);
					$fftype[] = JHTML::_('select.option', $field_type, $field_friendlyname);
				}
				$fftype[] = JHTML::_('select.optgroup', '' );
			}
			
			$fieldname = FLEXI_J16GE ? 'jform[field_type]' : 'field_type';
			$elementid = FLEXI_J16GE ? 'jform_field_type'  : 'field_type';
			$list = JHTML::_('select.genericlist', $fftype, $fieldname, $attribs, 'value', 'text', $selected, $elementid );
			if (!FLEXI_J16GE) $list = str_replace('<optgroup label="">', '</optgroup>', $list);
		}
		return $list;
	}

	/**
	 * Method to build the file extension list
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildfilesextlist($name, $class, $selected, $type=1)
	{
		$db = JFactory::getDBO();

		$query = 'SELECT DISTINCT ext'
		. ' FROM #__flexicontent_files'
		. ' ORDER BY ext ASC'
		;
		$db->setQuery($query);
		$exts = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
		
		if (!is_numeric($type)) {
			$options[] = JHTML::_( 'select.option', '', $type);
		} else {
			$options[] = JHTML::_( 'select.option', '', JText::_( 'FLEXI_ALL_EXT' ));
		}
		
		foreach ($exts as $ext) {
			$options[] = JHTML::_( 'select.option', $ext, $ext);
		}

		$list = JHTML::_('select.genericlist', $options, $name, $class, 'value', 'text', $selected );

		return $list;
	}

	/**
	 * Method to build the uploader list
	 *
	 * @return array
	 * @since 1.5
	 */
	static function builduploaderlist($name, $class, $selected, $type=1)
	{
		$db = JFactory::getDBO();

		$query = 'SELECT DISTINCT f.uploaded_by AS uid, u.name AS name'
		. ' FROM #__flexicontent_files AS f'
		. ' LEFT JOIN #__users AS u ON u.id = f.uploaded_by'
		. ' ORDER BY f.ext ASC'
		;
		$db->setQuery($query);
		$exts = $db->loadObjectList();
		
		if (!is_numeric($type)) {
			$options[] = JHTML::_( 'select.option', '', $type);
		} else {
			$options[] = JHTML::_( 'select.option', '', JText::_( 'FLEXI_ALL_UPLOADERS' ));
		}
		
		foreach ($exts as $ext) {
			$options[] = JHTML::_( 'select.option', $ext->uid, $ext->name);
		}

		$list = JHTML::_('select.genericlist', $options, $name, $class, 'value', 'text', $selected );

		return $list;
	}


	/**
	 * Method to build the Joomfish languages list
	 *
	 * @return object
	 * @since 1.5
	 */
	static function buildlanguageslist($name, $attribs, $selected, $type=1, $allowed_langs=null, $published_only=true, $disable_langs=null, $add_all=true, $conf=false)
	{
		$db = JFactory::getDBO();

		$selected_found = false;
		$all_langs = FLEXIUtilities::getlanguageslist($published_only, $add_all);
		$user_langs = null;
		if ($allowed_langs) {
			$_allowed = array_flip($allowed_langs);
			foreach ($all_langs as $index => $lang)
				if ( isset($_allowed[$lang->code] ) ) {
					$user_langs[] = $lang;
					// Check if selected language was added to the user langs
					$selected_found = ($lang->code == $selected) ? true : $selected_found;
				}
		} else {
			$user_langs = & $all_langs;
			$selected_found = true;
		}
		
		if ($disable_langs) {
			$_disabled = array_flip($disable_langs);
			$_user_langs = array();
			foreach ($user_langs as $index => $lang) {
				if ( !isset($_disabled[$lang->code] ) ) {
					$_user_langs[] = $lang;
					// Check if selected language was added to the user langs
					$selected_found = ($lang->code == $selected) ? true : $selected_found;
				}
			}
			$user_langs = $_user_langs;
		}
		
		if ( !count($user_langs) )  return "user is not allowed to use any language";
		if (!$selected_found) $selected = $user_langs[0]->code;  // Force first language to be selected
		
		$element_id = preg_replace('#[\[\]]#', '_', $name);
		
		
		if ( $conf && empty($conf['flags']) && empty($conf['texts']) ) {
			 $conf['flags'] = $conf['texts'] = 1;
		}
		
		$langs = array();
		switch ($type)
		{
			case 1: case 2: default:
				if ($type==1) {
					// Drop-down SELECT of ALL languages , WITHOUT empty prompt to select language
				} else if ($type==2) {
				  // Drop-down SELECT of ALL languages , WITH empty prompt to select language, e.g. used in items/category manager
					$langs[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_SELECT_LANGUAGE' ));
				} else if (!is_numeric($type)) {
				  // Drop-down SELECT of ALL languages , WITH custom prompt to select language
					$langs[] = JHTML::_('select.option',  '', $type);
				}
				foreach ($user_langs as $lang) {
					$langs[] = JHTML::_('select.option',  $lang->code, $lang->name );
				}
				$list = JHTML::_('select.genericlist', $langs, $name, $attribs, 'value', 'text', $selected );
				break;
			
			// RADIO selection of ALL languages , e.g. item form,
			case 3:   // flag icons only
				$checked	= '';
				$list		= '';

				foreach ($user_langs as $lang) {
					if ($lang->code == $selected) {
						$checked = ' checked="checked"';
					}
					$list 	.= '<input id="'.$element_id.$lang->id.'" type="radio" name="'.$name.'" value="'.$lang->code.'"'.$checked.' />';
					$list 	.= '<label class="lang_box" for="'.$element_id.$lang->id.'" title="'.$lang->name.'" >';
					if($lang->shortcode=="*") {
						$list 	.= '<span class="lang_lbl">'.JText::_('FLEXI_ALL').'</span>';  // Can appear in J1.6+ only
					} else {
						// Add Flag if configure and it exists
						if (!$conf || $conf['flags']) {
							$list .= !empty($lang->imgsrc)  ?  '<img class="lang_lbl" src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />'  :  $lang->code;
						}
						
						// Add text if configured
						if ( !$conf || $conf['texts']==1 ) {
							$list .= $lang->code;
						} else if ( $conf['texts']==2 ) {
							$list .= $lang->title;
						} else if ( $conf['texts']==3 ) {
							$list .= $lang->title_native;
						} else if ( $conf['texts']==4 ) {
							$list .= $lang->name;
						} else {
							$list .= '';
						}
					}
					
					$list 	.= '</label>';
					$checked	= '';
				}
				break;
			case 4:   // RADIO selection of ALL languages, with empty default option "Keep original language", e.g. when copying/moving items
				$list  = '<input id="lang9999" type="radio" name="'.$name.'" class="lang" value="" checked="checked" />';
				$list .= '<label class="lang_box" for="lang9999" title="'.JText::_( 'FLEXI_NOCHANGE_LANGUAGE_DESC' ).'" >';
				$list .= JText::_( 'FLEXI_NOCHANGE_LANGUAGE' );
				$list .= '</label><div class="clear"></div>';

				foreach ($user_langs as $lang) {
					$list 	.= '<input id="'.$element_id.$lang->id.'" type="radio" name="'.$name.'" class="lang" value="'.$lang->code.'" />';
					$list 	.= '<label class="lang_box" for="'.$element_id.$lang->id.'" title="'.$lang->name.'">';
					if($lang->shortcode=="*") {
						$list 	.= JText::_('FLEXI_ALL');  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '&nbsp;</label><div class="clear"></div>';
				}
				break;
			case 5:   // RADIO selection of ALL languages, EXCLUDE selected language, e.g. when translating items into another language
				$list		= '';
				foreach ($user_langs as $lang) {
					if ($lang->code==$selected) continue;
					$list 	.= '<input id="'.$element_id.$lang->id.'" type="radio" name="'.$name.'" class="lang" value="'.$lang->code.'" />';
					$list 	.= '<label class="lang_box" for="'.$element_id.$lang->id.'" title="'.$lang->name.'">';
					if($lang->shortcode=="*") {
						$list 	.= JText::_('FLEXI_ALL');  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '</label><div class="clear"></div>';
				}
				break;
			case 6:   // CHECK-BOX selection of ALL languages, with empty option "Use language column", e.g. used in CSV import view
				$list		= '';
				foreach ($user_langs as $lang) {
					$checked = $lang->code==$selected ? 'checked="checked"' : '';
					$list 	.= '<input id="'.$element_id.$lang->id.'" type="radio" name="'.$name.'" class="lang" value="'.$lang->code.'" '.$checked.'/>';
					$list 	.= '<label class="lang_box" for="'.$element_id.$lang->id.'" title="'.$lang->name.'">';
					if($lang->shortcode=="*") {
						$list 	.= JText::_('FLEXI_ALL');  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '&nbsp;</label>';
				}
				$checked = $selected==='' ? 'checked="checked"' : '';
				$list 	.= '<input id="lang9999" type="radio" name="'.$name.'" class="lang" value="" '.$checked.'/>';
				$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
				$tooltip_title = flexicontent_html::getToolTip('FLEXI_USE_LANGUAGE_COLUMN', 'FLEXI_USE_LANGUAGE_COLUMN_TIP', 1, 1);
				$list 	.= '<label class="lang_box'.$tooltip_class.'" for="lang9999" title="'.$tooltip_title.'">';
				$list 	.= JText::_( 'FLEXI_USE_LANGUAGE_COLUMN' );
				$list 	.= '</label>';
				break;
		}
		return $list;
	}

	/**
	 * Method to build the Joomfish languages list
	 *
	 * @return object
	 * @since 1.5
	 */
	static function buildstateslist($name, $attribs, $selected, $type=1)
	{
	 	static $state_names = null;
	 	static $state_descrs = null;
	 	static $state_imgs = null;
	 	if ( !$state_names ) {
			$state_names = array(1=>JText::_('FLEXI_PUBLISHED'), -5=>JText::_('FLEXI_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISHED'), -3=>JText::_('FLEXI_PENDING'), -4=>JText::_('FLEXI_TO_WRITE'), (FLEXI_J16GE ? 2:-1)=>JText::_('FLEXI_ARCHIVED'), -2=>JText::_('FLEXI_TRASHED'), ''=>'FLEXI_UNKNOWN');
			$state_descrs = array(1=>JText::_('FLEXI_PUBLISH_THIS_ITEM'), -5=>JText::_('FLEXI_SET_ITEM_IN_PROGRESS'), 0=>JText::_('FLEXI_UNPUBLISH_THIS_ITEM'), -3=>JText::_('FLEXI_SET_ITEM_PENDING'), -4=>JText::_('FLEXI_SET_ITEM_TO_WRITE'), (FLEXI_J16GE ? 2:-1)=>JText::_('FLEXI_ARCHIVE_THIS_ITEM'), -2=>JText::_('FLEXI_TRASH_THIS_ITEM'), ''=>'FLEXI_UNKNOWN');
			$state_imgs = array(1=>'tick.png', -5=>'publish_g.png', 0=>'publish_x.png', -3=>'publish_r.png', -4=>'publish_y.png', (FLEXI_J16GE ? 2:-1)=>'archive.png', -2=>'trash.png', ''=>'unknown.png');
		}
		
		$state[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_DO_NOT_CHANGE' ) );
		$state[] = JHTML::_('select.option',  -4, $state_names[-4] );
		$state[] = JHTML::_('select.option',  -3, $state_names[-3] );
		$state[] = JHTML::_('select.option',  -5, $state_names[-5] );
		$state[] = JHTML::_('select.option',   1, $state_names[1] );
		$state[] = JHTML::_('select.option',   0, $state_names[0] );
		$state[] = JHTML::_('select.option',  (FLEXI_J16GE ? 2:-1), $state_names[(FLEXI_J16GE ? 2:-1)] );
		$state[] = JHTML::_('select.option',  -2, $state_names[-2] );
		
		if ($type==1) {
			$list = JHTML::_('select.genericlist', $state, $name, $attribs, 'value', 'text', $selected );
		} else if ($type==2) {

			$state_ids   = array(1, -5, 0, -3, -4);
			$state_ids[] = FLEXI_J16GE ? 2:-1;
			$state_ids[]  = -2;

			$img_path = JURI::root(true)."/components/com_flexicontent/assets/images/";

			$list = '';
			foreach ($state_ids as $i => $state_id) {
				$checked = $state_id==$selected ? ' checked="checked"' : '';
				$list 	.= '<input id="state'.$state_id.'" type="radio" name="state" class="state" value="'.$state_id.'" '.$checked.'/>';
				$list 	.= '<label class="state_box" for="state'.$state_id.'" title="'.$state_names[$state_id].'" >';
				$list 	.= '<img src="'.$img_path.$state_imgs[$state_id].'" width="16" height="16" style="border-width:0;" alt="'.$state_names[$state_id].'" />';
				$list 	.= '</label>';
			}
			$checked = $selected==='' ? ' checked="checked"' : '';
			$list 	.= '<input id="state9999" type="radio" name="state" class="state" value="" '.$checked.'/>';
			$tooltip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
			$tooltip_title = flexicontent_html::getToolTip('FLEXI_USE_STATE_COLUMN', 'FLEXI_USE_STATE_COLUMN_TIP', 1, 1);
			$list 	.= '<label class="state_box'.$tooltip_class.'" for="state9999" title="'.$tooltip_title.'">';
			$list 	.= '<span class="state_lbl">'.JText::_( 'FLEXI_USE_STATE_COLUMN' ).'</span>';
			$list 	.= '</label>';
		} else {
			$list = 'Bad type in buildstateslist()';
		}

		return $list;
	}


	/**
	 * Method to get the user's Current Language
	 *
	 * @return string
	 * @since 1.5
	 */
	static function getUserCurrentLang()
	{
		static $lang = null;      // A two character language tag
		if ($lang) return $lang;

		// Get default content language for J1.5 and CURRENT content language for J2.5
		// NOTE: Content language can be natively switched in J2.5, by using
		// (a) the language switcher module and (b) the Language Filter - System Plugin
		$cntLang = substr(JFactory::getLanguage()->getTag(), 0,2);

		// Language as set in the URL (can be switched via Joomfish in J1.5)
		$urlLang  = JRequest::getWord('lang', '' );

		// Language from URL is used only in J1.5 -- (As said above, in J2.5 the content language can be switched natively)
		$lang = (FLEXI_J16GE || empty($urlLang)) ? $cntLang : $urlLang;

		// WARNING !!!: This variable is wrongly set in J2.5, maybe correct it?
		//JRequest::setVar('lang', $lang );

		return $lang;
	}


	/**
	 * Method to get Site (Frontend) default language
	 * NOTE: ... this is the default language of created content for J1.5, but in J1.6+ is '*' (=all)
	 * NOTE: ... joomfish creates translations in all other languages
	 *
	 * @return string
	 * @since 1.5
	 */
	static function getSiteDefaultLang()
	{
		$languages = JComponentHelper::getParams('com_languages');
		$lang = $languages->get('site', 'en-GB');
		return $lang;
	}

	static function nl2space($string) {
		if(gettype($string)!="string") return false;
		$strlen = strlen($string);
		$array = array();
		$str = "";
		for($i=0;$i<$strlen;$i++) {
			if(ord($string[$i])===ord("\n")) {
				$str .= ' ';
				continue;
			}
			$str .= $string[$i];
		}
		return $str;
	 }


	/**
		Diff implemented in pure php, written from scratch.
		Copyright (C) 2003  Daniel Unterberger <diff.phpnet@holomind.de>
		Copyright (C) 2005  Nils Knappmeier next version

		This program is free software; you can redistribute it and/or
		modify it under the terms of the GNU General Public License
		as published by the Free Software Foundation; either version 2
		of the License, or (at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

		http://www.gnu.org/licenses/gpl.html

		About:
		I searched a function to compare arrays and the array_diff()
		was not specific enough. It ignores the order of the array-values.
		So I reimplemented the diff-function which is found on unix-systems
		but this you can use directly in your code and adopt for your needs.
		Simply adopt the formatline-function. with the third-parameter of arr_diff()
		you can hide matching lines. Hope someone has use for this.

		Contact: d.u.diff@holomind.de <daniel unterberger>
	**/

	## PHPDiff returns the differences between $old and $new, formatted
	## in the standard diff(1) output format.

	static function PHPDiff($t1,$t2)
	{
		# split the source text into arrays of lines
		//$t1 = explode("\n",$old);
		$x=array_pop($t1);
		if ($x>'') $t1[]="$x\n\\ No newline at end of file";
		//$t2 = explode("\n",$new);
		$x=array_pop($t2);
		if ($x>'') $t2[]="$x\n\\ No newline at end of file";

		# build a reverse-index array using the line as key and line number as value
		# don't store blank lines, so they won't be targets of the shortest distance
		# search
		foreach($t1 as $i=>$x) if ($x>'') $r1[$x][]=$i;
		foreach($t2 as $i=>$x) if ($x>'') $r2[$x][]=$i;

		$a1=0; $a2=0;   # start at beginning of each list
		$actions=array();

		# walk this loop until we reach the end of one of the lists
		while ($a1<count($t1) && $a2<count($t2))
		{
			# if we have a common element, save it and go to the next
			if ($t1[$a1]==$t2[$a2]) { $actions[]=4; $a1++; $a2++; continue; }

			# otherwise, find the shortest move (Manhattan-distance) from the
			# current location
			$best1=count($t1); $best2=count($t2);
			$s1=$a1; $s2=$a2;
			while(($s1+$s2-$a1-$a2) < ($best1+$best2-$a1-$a2)) {
			$d=-1;
			foreach((array)@$r1[$t2[$s2]] as $n)
			if ($n>=$s1) { $d=$n; break; }
			if ($d>=$s1 && ($d+$s2-$a1-$a2)<($best1+$best2-$a1-$a2))
			{ $best1=$d; $best2=$s2; }
			$d=-1;
			foreach((array)@$r2[$t1[$s1]] as $n)
			if ($n>=$s2) { $d=$n; break; }
			if ($d>=$s2 && ($s1+$d-$a1-$a2)<($best1+$best2-$a1-$a2))
			{ $best1=$s1; $best2=$d; }
			$s1++; $s2++;
			}
			while ($a1<$best1) { $actions[]=1; $a1++; }  # deleted elements
			while ($a2<$best2) { $actions[]=2; $a2++; }  # added elements
		}

		# we've reached the end of one list, now walk to the end of the other
		while($a1<count($t1)) { $actions[]=1; $a1++; }  # deleted elements
		while($a2<count($t2)) { $actions[]=2; $a2++; }  # added elements

		# and this marks our ending point
		$actions[]=8;

		# now, let's follow the path we just took and report the added/deleted
		# elements into $out.
		$op = 0;
		$x0=$x1=0; $y0=$y1=0;
		$out1 = array();
		$out2 = array();
		foreach($actions as $act) {
			if ($act==1) { $op|=$act; $x1++; continue; }
			if ($act==2) { $op|=$act; $y1++; continue; }
			if ($op>0) {
				//$xstr = ($x1==($x0+1)) ? $x1 : ($x0+1).",$x1";
				//$ystr = ($y1==($y0+1)) ? $y1 : ($y0+1).",$y1";
				/*if ($op==1) $out[] = "{$xstr}d{$y1}";
				elseif ($op==3) $out[] = "{$xstr}c{$ystr}";*/
				while ($x0<$x1) { $out1[] = $x0; $x0++; }   # deleted elems
				/*if ($op==2) $out[] = "{$x1}a{$ystr}";
				elseif ($op==3) $out[] = '---';*/
				while ($y0<$y1) { $out2[] = $y0; $y0++; }   # added elems
			}
			$x1++; $x0=$x1;
			$y1++; $y0=$y1;
			$op=0;
		}
		//$out1[] = '';
		//$out2[] = '';
		return array($out1, $out2);
	}

	static function flexiHtmlDiff($old, $new, $mode=0)
	{
		$t1 = explode(" ",$old);
		$t2 = explode(" ",$new);
		$out = flexicontent_html::PHPDiff( $t1, $t2 );
		$html1 = array();
		$html2 = array();
		foreach($t1 as $k=>$o) {
			if(in_array($k, $out[0])) $html1[] = "<s>".($mode?htmlspecialchars($o, ENT_QUOTES):$o)."</s>";
			else $html1[] = ($mode?htmlspecialchars($o, ENT_QUOTES)."<br />":$o);
		}
		foreach($t2 as $k=>$n) {
			if(in_array($k, $out[1])) $html2[] = "<u>".($mode?htmlspecialchars($n, ENT_QUOTES):$n)."</u>";
			else $html2[] = ($mode?htmlspecialchars($n, ENT_QUOTES)."<br />":$n);
		}
		$html1 = implode(" ", $html1);
		$html2 = implode(" ", $html2);
		return array($html1, $html2);
	}


	/**
	 * Method to retrieve mappings of CORE fields (Names to Types and reverse)
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getJCoreFields($ffield=NULL, $map_maintext_to_introtext=false, $reverse=false) {
		if(!$reverse)  // MAPPING core fields NAMEs => core field TYPEs
		{
			$flexifield = array(
				'title'=>'title',
				'categories'=>'categories',
				'tags'=>'tags',
				'text'=>'maintext',
				'created'=>'created',
				'created_by'=>'createdby',
				'modified'=>'modified',
				'modified_by'=>'modifiedby',
				'hits'=>'hits',
				'document_type'=>'type',
				'version'=>'version',
				'state'=>'state'
			);
			if ($map_maintext_to_introtext)
			{
				$flexifield['introtext'] = 'maintext';
			}
		}
		else    // MAPPING core field TYPEs => core fields NAMEs
		{
			$flexifield = array(
				'title'=>'title',
				'categories'=>'categories',
				'tags'=>'tags',
				'maintext'=>'text',
				'created'=>'created',
				'createdby'=>'created_by',
				'modified'=>'modified',
				'modifiedby'=>'modified_by',
				'hits'=>'hits',
				'type'=>'document_type',
				'version'=>'version',
				'state'=>'state'
			);
			if ($map_maintext_to_introtext)
			{
				$flexifield['maintext'] = 'introtext';
			}
		}
		if($ffield===NULL) return $flexifield;
		return isset($flexifield[$ffield])?$flexifield[$ffield]:NULL;
	}

	static function getFlexiFieldId($jfield=NULL) {
		$flexifields = array(
			'introtext'=>1,
			'text'=>1,
			'created'=>2,
			'created_by'=>3,
			'modified'=>4,
			'modified_by'=>5,
			'title'=>6,
			'hits'=>7,
			'version'=>9,
			'state'=>10,
			'catid'=>13,
		);
		if($jfield===NULL) return $flexifields;
		return isset($flexifields[$jfield])?$flexifields[$jfield]:0;
	}

	static function getFlexiField($jfield=NULL) {
		$flexifields = array(
			'introtext'=>'text',
			'fulltext'=>'text',
			'created'=>'created',
			'created_by'=>'createdby',
			'modified'=>'modified',
			'modified_by'=>'modifiedby',
			'title'=>'title',
			'hits'=>'hits',
			'version'=>'version',
			'state'=>'state'
		);
		if($jfield===NULL) return $flexifields;
		return isset($flexifields[$jfield])?$flexifields[$jfield]:0;
	}

	static function getTypesList()
	{
		$db = JFactory::getDBO();

		$query = 'SELECT *'
		. ' FROM #__flexicontent_types'
		. ' WHERE published = 1'
		;

		$db->setQuery($query);
		$types = $db->loadAssocList('id');

		return $types;
	}


	/**
	 * Displays a list of the available access view levels
	 *
	 * @param	string	The form field name.
	 * @param	string	The name of the selected section.
	 * @param	string	Additional attributes to add to the select field.
	 * @param	mixed	True to add "All Sections" option or and array of option
	 * @param	string	The form field id
	 *
	 * @return	string	The required HTML for the SELECT tag.
	 */
	static function userlevel($name, $selected, $attribs = '', $params = true, $id = false, $createlist = true) {
		static $options;
		if(!$options) {
			$db		= JFactory::getDbo();
			$query	= $db->getQuery(true);
			$query->select('a.id AS value, a.title AS text');
			$query->from('#__viewlevels AS a');
			if (!$createlist) {
				$query->where('a.id="'.$selected.'"');
			}
			$query->group('a.id');
			$query->order('a.ordering ASC');
			$query->order('`title` ASC');

			// Get the options.
			$db->setQuery($query);
			$options = $db->loadObjectList();

			// Check for a database error.
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
			if ( !$options ) return null;

			if (!$createlist) {
				return $options[0]->text;  // return ACCESS LEVEL NAME
			}

			// If params is an array, push these options to the array
			if (is_array($params)) {
				$options = array_merge($params,$options);
			}
			// If all levels is allowed, push it into the array.
			elseif ($params) {
				//array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_ACCESS_SHOW_ALL_LEVELS')));
			}
		}

		return JHtml::_('select.genericlist', $options, $name,
			array(
				'list.attr' => $attribs,
				'list.select' => $selected,
				'id' => $id
			)
		);
	}
	
	
	/*
	 * Method to create a Tabset for given label-html arrays
	 * param  string			$date
	 * return boolean			true if valid date, false otherwise
	 */
	static function createFieldTabber( &$field_html, &$field_tab_labels, $class )
	{
		$not_in_tabs = "";

		$output = "<!-- tabber start --><div class='fctabber ".$class."'>"."\n";

		foreach ($field_html as $i => $html) {
			// Hide field when it has no label, and skip creating tab
			$no_label = ! isset( $field_tab_labels[$i] );
			$not_in_tabs .= $no_label ? "<div style='display:none!important'>".$field_html[$i]."</div>" : "";
			if ( $no_label ) continue;

			$output .= "	<div class='tabbertab'>"."\n";
			$output .= "		<h3 class='tabberheading'>".$field_tab_labels[$i]."</h3>"."\n";   // Current TAB LABEL
			$output .= "		".$not_in_tabs."\n";                        // Output hidden fields (no tab created), by placing them inside the next appearing tab
			$output .= "		".$field_html[$i]."\n";                     // Current TAB CONTENTS
			$output .= "	</div>"."\n";

			$not_in_tabs = "";     // Clear the hidden fields variable
		}
		$output .= "</div><!-- tabber end -->";
		$output .= $not_in_tabs;      // Output ENDING hidden fields, by placing them outside the tabbing area
		return $output;
	}

	static function addToolBarButton($text='Button Text', $name='btnname', $full_js='', $err_msg='', $confirm_msg='', $task='btntask', $extra_js='', $list=true, $menu=true, $confirm=true, $btn_class="")
	{
		$toolbar = JToolBar::getInstance('toolbar');
		$text  = JText::_( $text );
		$class = 'icon-32-'.$name;

		if ( !$full_js )
		{
			$err_msg = $err_msg ? $err_msg : JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', $name );
			$err_msg = addslashes($err_msg);
			$confirm_msg = $confirm_msg ? $confirm_msg : JText::_('FLEXI_ARE_YOU_SURE');

			$full_js = $extra_js ."; submitbutton('$task');";
			if ($confirm) {
				$full_js = "if (confirm('".$confirm_msg."')) { ".$full_js." }";
			}
			if (!$menu) {
				$full_js = "hideMainMenu(); " . $full_js;
			}
			if ($list) {
				$full_js = "if (document.adminForm.boxchecked.value==0) { alert('".$err_msg."') ;} else { ".$full_js." }";
			}
		}
		$full_js = "javascript: $full_js";

		$button_html	= "<a href=\"#\" onclick=\"$full_js\" class=\"toolbar btn btn-small $btn_class\">\n";
		$button_html .= "<span class=\"$class\" title=\"$text\">\n";
		$button_html .= "</span>\n";
		$button_html	.= "$text\n";
		$button_html	.= "</a>\n";

		$toolbar->appendButton('Custom', $button_html, $name);
	}
	
	
	// ************************************************************************
	// Calculate CSS classes needed to add special styling markups to the items
	// ************************************************************************
	static function	calculateItemMarkups($items, $params)
	{
		global $globalcats;
		global $globalnoroute;
		$globalnoroute = !is_array($globalnoroute) ? array() : $globalnoroute;
		
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();
		$aids = FLEXI_J16GE ? JAccess::getAuthorisedViewLevels($user->id) : array((int) $user->get('aid'));
		
		
		// **************************************
		// Get configuration about markups to add
		// **************************************
		
		// Get addcss parameters
		$mu_addcss_cats = $params->get('mu_addcss_cats', array('featured'));
		$mu_addcss_cats = FLEXIUtilities::paramToArray($mu_addcss_cats);
		$mu_addcss_acclvl = $params->get('mu_addcss_acclvl', array('needed_acc', 'obtained_acc'));
		$mu_addcss_acclvl = FLEXIUtilities::paramToArray($mu_addcss_acclvl);
		$mu_addcss_radded   = $params->get('mu_addcss_radded', 0);
		$mu_addcss_rupdated = $params->get('mu_addcss_rupdated', 0);
		
		// Calculate addcss flags
		$add_featured_cats = in_array('featured', $mu_addcss_cats);
		$add_other_cats    = in_array('other', $mu_addcss_cats);
		$add_no_acc        = in_array('no_acc', $mu_addcss_acclvl);
		$add_free_acc      = in_array('free_acc', $mu_addcss_acclvl);
		$add_needed_acc    = in_array('needed_acc', $mu_addcss_acclvl);
		$add_obtained_acc  = in_array('obtained_acc', $mu_addcss_acclvl);
		
		// Get addtext parameters
		$mu_addtext_cats   = $params->get('mu_addtext_cats', 1);
		$mu_addtext_acclvl = $params->get('mu_addtext_acclvl', array('no_acc', 'free_acc', 'needed_acc', 'obtained_acc'));
		$mu_addtext_acclvl = FLEXIUtilities::paramToArray($mu_addtext_acclvl);
		$mu_addtext_radded   = $params->get('mu_addtext_radded', 1);
		$mu_addtext_rupdated = $params->get('mu_addtext_rupdated', 1);
		
		// Calculate addtext flags
		$add_txt_no_acc       = in_array('no_acc', $mu_addtext_acclvl);
		$add_txt_free_acc     = in_array('free_acc', $mu_addtext_acclvl);
		$add_txt_needed_acc   = in_array('needed_acc', $mu_addtext_acclvl);
		$add_txt_obtained_acc = in_array('obtained_acc', $mu_addtext_acclvl);
		
		$mu_add_condition_obtainded_acc = $params->get('mu_add_condition_obtainded_acc', 1);
		
		$mu_no_acc_text   = JText::_( $params->get('mu_no_acc_text',   'FLEXI_MU_NO_ACC') );
		$mu_free_acc_text = JText::_( $params->get('mu_free_acc_text', 'FLEXI_MU_NO_ACC') );
		
		
		// *******************************
		// Prepare data needed for markups
		// *******************************
		
		// a. Get Featured categories and language filter their titles
		$featured_cats_parent = $params->get('featured_cats_parent', 0);
		$disabled_cats = $params->get('featured_cats_parent_disable', 1) ? array($featured_cats_parent) : array();
		$featured_cats = array();
		if ( $add_featured_cats && $featured_cats_parent )
		{
			$where[] = isset($globalcats[$featured_cats_parent])  ?
				'id IN (' . $globalcats[$featured_cats_parent]->descendants . ')' :
				'parent_id = '. $featured_cats_parent
				;
			if (!empty($disabled_cats)) $where[] = 'id NOT IN (' . implode(", ", $disabled_cats) . ')';  // optionally exclude category root of featured subtree
			$query = 'SELECT c.id'
				. ' FROM #__categories AS c'
				. (count($where) ? ' WHERE ' . implode( ' AND ', $where ) : '')
				;
			$db->setQuery($query);
			
			$featured_cats = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			$featured_cats = $featured_cats ? array_flip($featured_cats) : array();
			
			foreach ($featured_cats as $featured_cat => $i)
			{
				$featured_cats_titles[$featured_cat] = JText::_($globalcats[$featured_cat]->title);
			}
		}
		
		
		// b. Get Access Level names (language filter them)
		if ( $add_needed_acc || $add_obtained_acc )
		{
			if (FLEXI_J16GE) {
				$db->setQuery('SELECT id, title FROM #__viewlevels');
				$_arr = $db->loadObjectList();
				$access_names = array(0=>'Public');  // zero does not exist in J2.5+ but we set it for compatibility
				foreach ($_arr as $o) $access_names[$o->id] = JText::_($o->title);
			} else {
				$access_names = array(0=>'Public', 1=>'Registered', 2=>'Special', 3=>'Privileged');
			}
		}
		
		
		// c. Calculate creation time intervals
		if ( $mu_addcss_radded )
		{
		  $nowdate_secs = time();
			$ra_timeframes = $params->get('mu_ra_timeframe_intervals', '24h,2d,7d,1m,3m,1y,3y');
			$ra_timeframes = preg_split("/\s*,\s*/u", $ra_timeframes);
			
			$ra_names = $params->get('mu_ra_timeframe_names', 'FLEXI_24H_RA , FLEXI_2D_RA , FLEXI_7D_RA , FLEXI_1M_RA , FLEXI_3M_RA , FLEXI_1Y_RA , FLEXI_3Y_RA');
			$ra_names = preg_split("/\s*,\s*/u", $ra_names);
			
			$unit_hour_map = array('h'=>1, 'd'=>24, 'm'=>24*30, 'y'=>24*365);
			$unit_word_map = array('h'=>'hours', 'd'=>'days', 'm'=>'months', 'y'=>'years');
			$unit_text_map = array(
				'h'=>'FLEXI_MU_HOURS', 'd'=>'FLEXI_MU_DAYS', 'm'=>'FLEXI_MU_MONTHS', 'y'=>'FLEXI_MU_YEARS'
			);
			foreach($ra_timeframes as $i => $timeframe) {
				$unit = substr($timeframe, -1);
				if ( !isset($unit_hour_map[$unit]) ) {
					echo "Improper timeframe ': ".$timeframe."' for recently added content, please fix in configuration";
					continue;
				}
				$timeframe  = (int) $timeframe;
				$ra_css_classes[$i] = '_item_added_within_' . $timeframe . $unit_word_map[$unit];
				$ra_timeframe_secs[$i] = $timeframe * $unit_hour_map[$unit] * 3600;
				$ra_timeframe_text[$i] = @ $ra_names[$i] ? JText::_($ra_names[$i]) : JText::_('FLEXI_MU_ADDED') . JText::sprintf($unit_text_map[$unit], $timeframe);
			}
		}
		
		
		// d. Calculate updated time intervals
		if ( $mu_addcss_rupdated )
		{
		  $nowdate_secs = time();
			$ru_timeframes = $params->get('mu_ru_timeframe_intervals', '24h,2d,7d,1m,3m,1y,3y');
			$ru_timeframes = preg_split("/\s*,\s*/u", $ru_timeframes);
			
			$ru_names = $params->get('mu_ru_timeframe_names', 'FLEXI_24H_RU , FLEXI_2D_RU , FLEXI_7D_RU , FLEXI_1M_RU , FLEXI_3M_RU , FLEXI_1Y_RU , FLEXI_3Y_RU');
			$ru_names = preg_split("/\s*,\s*/u", $ru_names);
			
			$unit_hour_map = array('h'=>1, 'd'=>24, 'm'=>24*30, 'y'=>24*365);
			$unit_word_map = array('h'=>'hours', 'd'=>'days', 'm'=>'months', 'y'=>'years');
			$unit_text_map = array(
				'h'=>'FLEXI_MU_HOURS', 'd'=>'FLEXI_MU_DAYS', 'm'=>'FLEXI_MU_MONTHS', 'y'=>'FLEXI_MU_YEARS'
			);
			foreach($ru_timeframes as $i => $timeframe) {
				$unit = substr($timeframe, -1);
				if ( !isset($unit_hour_map[$unit]) ) {
					echo "Improper timeframe ': ".$timeframe."' for recently updated content, please fix in configuration";
					continue;
				}
				$timeframe  = (int) $timeframe;
				$ru_css_classes[$i] = '_item_updated_within_' . $timeframe . $unit_word_map[$unit];
				$ru_timeframe_secs[$i] = $timeframe * $unit_hour_map[$unit] * 3600;
				$ru_timeframe_text[$i] = @ $ru_names[$i] ? JText::_($ru_names[$i]) : JText::_('FLEXI_MU_UPDATED') . JText::sprintf($unit_text_map[$unit], $timeframe);
			}
		}
		
		
		// **********************************
		// Create CSS markup classes per item
		// **********************************
		$public_acclvl = FLEXI_J16GE ? 1 : 0;
		foreach ($items as $item) 
		{
			$item->css_markups = array();
			
			
			// Category markups
			if ( $add_featured_cats || $add_other_cats ) foreach ($item->categories as $item_cat) {
				$is_featured_cat = isset( $featured_cats[$item_cat->id] );
				
				if ( $is_featured_cat && !$add_featured_cats  ) continue;   // not adding featured cats
				if ( !$is_featured_cat && !$add_other_cats  )   continue;   // not adding other cats
				if ( in_array($item_cat->id, $globalnoroute) )	continue;   // non-linkable/routable 'special' category
				
				$item->css_markups['itemcats'][] = '_itemcat_'.$item_cat->id;
				$item->ecss_markups['itemcats'][] = ($is_featured_cat ? ' mu_featured_cat' : ' mu_normal_cat') . ($mu_addtext_cats ? ' mu_has_text' : '');
				$item->title_markups['itemcats'][] = $mu_addtext_cats  ?  ($is_featured_cat ? $featured_cats_titles[$item_cat->id] : $globalcats[$item_cat->id]->title)  :  '';
			}
			
			
			// recently-added Timeframe markups
			if ($mu_addcss_radded) {
				$item_timeframe_secs = $nowdate_secs - strtotime($item->created);
				$mr = -1;
				
				foreach($ra_timeframe_secs as $i => $timeframe_secs) {
					// Check if item creation time has surpassed this time frame
					if ( $item_timeframe_secs > $timeframe_secs) continue;
					
					// Check if this time frame is more recent than the best one found so far
					if ($mr != -1 && $timeframe_secs > $ra_timeframe_secs[$mr]) continue;
					
					// Use current time frame
					$mr = $i;
			  }
				if ($mr >= 0) {
					$item->css_markups['timeframe'][] = $ra_css_classes[$mr];
					$item->ecss_markups['timeframe'][] = ' mu_ra_timeframe' . ($mu_addtext_radded ? ' mu_has_text' : '');
					$item->title_markups['timeframe'][] = $mu_addtext_radded ? $ra_timeframe_text[$mr] : '';
				}
			}
			
			
			// recently-updated Timeframe markups
			if ($mu_addcss_rupdated) {
				$item_timeframe_secs = $nowdate_secs - strtotime($item->modified);
				$mr = -1;
				
				foreach($ru_timeframe_secs as $i => $timeframe_secs) {
					// Check if item creation time has surpassed this time frame
					if ( $item_timeframe_secs > $timeframe_secs) continue;
					
					// Check if this time frame is more recent than the best one found so far
					if ($mr != -1 && $timeframe_secs > $ru_timeframe_secs[$mr]) continue;
					
					// Use current time frame
					$mr = $i;
			  }
				if ($mr >= 0) {
					$item->css_markups['timeframe'][] = $ru_css_classes[$mr];
					$item->ecss_markups['timeframe'][] = ' mu_ru_timeframe' . ($mu_addtext_rupdated ? ' mu_has_text' : '');
					$item->title_markups['timeframe'][] = $mu_addtext_rupdated ? $ru_timeframe_text[$mr] : '';
				}
			}
			
			
			// Get item's access levels if this is needed
			if ($add_free_acc || $add_needed_acc || $add_obtained_acc) {
				$all_acc_lvls = array();
				$all_acc_lvls[] = $item->access;
				$all_acc_lvls[] = $item->category_access;
				$all_acc_lvls[] = $item->type_access;
				$all_acc_lvls = array_unique($all_acc_lvls);
			}
			
			
			// No access markup
			if ($add_no_acc && !$item->has_access) {
				$item->css_markups['access'][]   = '_item_no_access';
				$item->ecss_markups['access'][] =  ($add_txt_no_acc ? ' mu_has_text' : '');
				$item->title_markups['access'][] = $add_txt_no_acc ? $mu_no_acc_text : '';
			}
			
			
			// Free access markup, Add ONLY if item has a single access level the public one ...
			if ( $add_free_acc && $item->has_access && count($all_acc_lvls)==1 && $public_acclvl == reset($all_acc_lvls) )
			{
				$item->css_markups['access'][]   = '_item_free_access';
				$item->ecss_markups['access'][]  = $add_txt_free_acc ? ' mu_has_text' : '';
				$item->title_markups['access'][] = $add_txt_free_acc ? $mu_free_acc_text : '';
			}
			
			
			// Needed / Obtained access levels markups
			if ($add_needed_acc || $add_obtained_acc)
			{
				foreach($all_acc_lvls as $all_acc_lvl)
				{
					if ($public_acclvl == $all_acc_lvl) continue;  // handled separately above
					
					$has_acclvl = FLEXI_J16GE ? in_array($all_acc_lvl, $aids) : $all_acc_lvl <= $aids;
					if (!$has_acclvl) {
						if (!$add_needed_acc) continue;   // not adding needed levels
						$item->css_markups['access'][] = '_acclvl_'.$all_acc_lvl;
						$item->ecss_markups['access'][] = ' mu_needed_acclvl' . ($add_txt_needed_acc ? ' mu_has_text' : '');
						$item->title_markups['access'][] = $add_txt_needed_acc ? $access_names[$all_acc_lvl] : '';
					} else {
						if (!$add_obtained_acc) continue; // not adding obtained levels
						if ($mu_add_condition_obtainded_acc==0 && !$item->has_access) continue;  // do not add obtained level markups if item is inaccessible
						$item->css_markups['access'][] = '_acclvl_'.$all_acc_lvl;
						$item->ecss_markups['access'][] = ' mu_obtained_acclvl' . ($add_txt_obtained_acc ? ' mu_has_text' : '');
						$item->title_markups['access'][] = $add_txt_obtained_acc ? $access_names[$all_acc_lvl] : '';
					}
				}
			}
		}
	}
	
}

class flexicontent_upload
{
	static function makeSafe($file) {//The range \xE01-\xE5B is thai language.
		$file = str_replace(" ", "", $file);
		$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\xE01-\xE5B\.\_\- ]#', '#^\.#');
		//$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\.\_\- ]#', '#^\.#');
		return preg_replace($regex, '', $file);
	}
	
	
	static function parseByteLimit($limit)
	{
		if (is_numeric($limit)) return $limit;  // already in bytes
	
		$v = (int)$limit;
		$type = substr($limit, -1);
		
		switch (strtoupper($type)) {
			case 'P': $v *= 1024;
			case 'T':	$v *= 1024;
			case 'G':	$v *= 1024;
			case 'M': $v *= 1024;
			case 'K': $v *= 1024;
			break;
		}
		return $v;
	}
	
	
	static function getPHPuploadLimit()
	{
		$post_max   = flexicontent_upload::parseByteLimit(ini_get('post_max_size'));
		$upload_max = flexicontent_upload::parseByteLimit(ini_get('upload_max_filesize'));
		if ($upload_max < $post_max) {
			$limit = array('value'=>$upload_max, 'name'=>'upload_max_filesize');
		}
		else {
			$limit = array('value'=>$post_max, 'name'=>'post_max_size');
		}
		// Sucosin limitation
		if (extension_loaded('suhosin')) {
			$post_max = flexicontent_upload::parseByteLimit(ini_get('suhosin.post.max_value_length'));
			if ($post_max < $limit['value']) $limit = array('value'=>$post_max, 'name'=>'suhosin.post.max_value_length');
		}
		return $limit;
	}


	/**
	 * Gets the extension of a file name
	 *
	 * @param string $file The file name
	 * @return string The file extension
	 * @since 1.5
	 */
	static function getExt($file) {
		$len = strlen($file);
		$params = JComponentHelper::getParams( 'com_flexicontent' );
		$exts = $params->get('upload_extensions');
		$exts = str_replace(' ', '', $exts);
		$exts = explode(",", $exts);
		//$exts = array('pdf', 'odt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'tar.gz');
		$ext = '';
		for($i=$len-1;$i>=0;$i--) {
			$c = $file[$i];
			if($c=='.' && in_array($ext, $exts)) {
				return $ext;
			}
			$ext = $c . $ext;
		}
		$dot = strpos($file, '.') + 1;
		return substr($file, $dot);
	}


	/**
	 * Checks uploaded file
	 *
	 * @param string $file The file name
	 * @param string $err  Set (return) the error string in it
	 * @param string $file view 's parameters
	 * @return string The file extension
	 * @since 1.5
	 */
	static function check(&$file, &$err, &$params)
	{
		if (!$params) {
			$params = JComponentHelper::getParams( 'com_flexicontent' );
		}

		if(empty($file['name'])) {
			$err = 'FLEXI_PLEASE_INPUT_A_FILE';
			return false;
		}

		jimport('joomla.filesystem.file');
		$file['altname'] = $file['name'];
		if ($file['name'] !== JFile::makesafe($file['name'])) {
			//$err = JText::_('FLEXI_WARNFILENAME').','.$file['name'].'|'.JFile::makesafe($file['name'])."<br />";
			//return false;
			$file['name'] = date('Y-m-d-H-i-s').".".flexicontent_upload::getExt($file['name']);
		}

		//check if the imagefiletype is valid
		$format 	= strtolower(flexicontent_upload::getExt($file['name']));

		$allowable = explode( ',', $params->get( 'upload_extensions' ));
		foreach($allowable as $a => $allowable_ext) $allowable[$a] = strtolower($allowable_ext);
		
		$ignored = explode(',', $params->get( 'ignore_extensions' ));
		foreach($ignored as $a => $ignored_ext) $ignored[$a] = strtolower($ignored_ext);
		if (!in_array($format, $allowable) && !in_array($format,$ignored))
		{
			$err = 'FLEXI_WARNFILETYPE';
			return false;
		}

		//Check filesize
		$maxSize = (int) $params->get( 'upload_maxsize', 0 );
		if ($maxSize > 0 && (int) $file['size'] > $maxSize)
		{
			$err = 'FLEXI_WARNFILETOOLARGE';
			return false;
		}

		$imginfo = null;

		$images = explode( ',', $params->get( 'image_extensions' ));

		if($params->get('restrict_uploads', 1) ) {

			if(in_array($format, $images)) { // if its an image run it through getimagesize
				if(($imginfo = getimagesize($file['tmp_name'])) === FALSE) {
					$err = 'FLEXI_WARNINVALIDIMG';
					return false;
				}

			} else if(!in_array($format, $ignored)) {

				// if its not an image...and we're not ignoring it
				$allowed_mime = explode(',', $params->get('upload_mime'));
				$illegal_mime = explode(',', $params->get('upload_mime_illegal'));

				if(function_exists('finfo_open') && $params->get('check_mime',1)) {
					// We have fileinfo
					$finfo = finfo_open(FILEINFO_MIME);
					$type = finfo_file($finfo, $file['tmp_name']);
					if(strlen($type) && !in_array($type, $allowed_mime) && in_array($type, $illegal_mime)) {
						$err = 'FLEXI_WARNINVALIDMIME';
						return false;
					}
					finfo_close($finfo);

				} else if(function_exists('mime_content_type') && $params->get('check_mime',1)) {

					// we have mime magic
					$type = mime_content_type($file['tmp_name']);

					if(strlen($type) && !in_array($type, $allowed_mime) && in_array($type, $illegal_mime)) {
						$err = 'FLEXI_WARNINVALIDMIME';
						return false;
					}

				}
			}
		}
		$xss_check =  JFile::read($file['tmp_name'],false,256);
		$html_tags = array('abbr','acronym','address','applet','area','audioscope','base','basefont',
			'bdo','bgsound','big','blackface','blink','blockquote','body','bq','br','button','caption',
			'center','cite','code','col','colgroup','comment','custom','dd','del','dfn','dir','div','dl','dt',
			'em','embed','fieldset','fn','font','form','frame','frameset','h1','h2','h3','h4','h5','h6','head',
			'hr','html','iframe','ilayer','img','input','ins','isindex','keygen','kbd','label','layer','legend',
			'li','limittext','link','listing','map','marquee','menu','meta','multicol','nobr','noembed','noframes',
			'noscript','nosmartquotes','object','ol','optgroup','option','param','plaintext','pre','rt','ruby','s','samp',
			'script','select','server','shadow','sidebar','small','spacer','span','strike','strong','style','sub','sup','table',
			'tbody','td','textarea','tfoot','th','thead','title','tr','tt','ul','var','wbr','xml','xmp','!DOCTYPE', '!--');
		foreach($html_tags as $tag) {
			// A tag is '<tagname ', so we need to add < and a space or '<tagname>'
			if(stristr($xss_check, '<'.$tag.' ') || stristr($xss_check, '<'.$tag.'>')) {
				$err = 'FLEXI_WARNIEXSS';
				return false;
			}
		}

		return true;
	}

	/**
	* Sanitize the image file name and return an unique string
	*
	* @since 1.0
	*
	* @param string $base_Dir the target directory
	* @param string $filename the unsanitized imagefile name
	*
	* @return string $filename the sanitized and unique file name
	*/
	static function sanitize($base_Dir, $filename)
	{
		jimport('joomla.filesystem.file');

		//check for any leading/trailing dots and remove them (trailing shouldn't be possible cause of the getEXT check)
		$filename = preg_replace( "/^[.]*/", '', $filename );
		$filename = preg_replace( "/[.]*$/", '', $filename ); //shouldn't be necessary, see above

		//we need to save the last dot position cause preg_replace will also replace dots
		$lastdotpos = strrpos( $filename, '.' );

		//replace invalid characters
		$chars = '[^0-9a-zA-Z()_-]';
		$filename 	= strtolower( preg_replace( "/$chars/", '-', $filename ) );

		//get the parts before and after the dot (assuming we have an extension...check was done before)
		$beforedot	= substr( $filename, 0, $lastdotpos );
		$afterdot 	= substr( $filename, $lastdotpos + 1 );

		//make a unique filename for the image and check it is not already taken
		//if it is already taken keep trying till success
		if (JFile::exists( $base_Dir . $beforedot . '.' . $afterdot ))
		{
			$version = 1;
			while( JFile::exists( $base_Dir . $beforedot . '-' . $version . '.' . $afterdot ) )
			{
				$version++;
			}
			//create out of the seperated parts the new filename
			$filename = $beforedot . '-' . $version . '.' . $afterdot;
		} else {
			$filename = $beforedot . '.' . $afterdot;
		}

		return $filename;
	}

	/**
	* Sanitize folders and return an unique string
	*
	* @since 1.5
	*
	* @param string $base_Dir the target directory
	* @param string $foler the unsanitized folder name
	*
	* @return string $foldername the sanitized and unique file name
	*/
	static function sanitizedir($base_Dir, $folder)
	{
		jimport('joomla.filesystem.folder');

		//replace invalid characters
		$chars = '[^0-9a-zA-Z()_-]';
		$folder 	= strtolower( preg_replace( "/$chars/", '-', $folder ) );

		//make a unique folder name for the image and check it is not already taken
		if (JFolder::exists( $base_Dir . $folder ))
		{
			$version = 1;
			while( JFolder::exists( $base_Dir . $folder . '-' . $version )) {
				$version++;
			}
			//create out of the seperated parts the new folder name
			$foldername = $folder . '-' . $version;
		} else {
			$foldername = $folder;
		}

		return $foldername;
	}
}



class flexicontent_tmpl
{
	/**
	 * Parse all FLEXIcontent templates files
	 *
	 * @return 	object	object of templates
	 * @since 1.5
	 */
	static function parseTemplates($tmpldir='')
	{
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');
		jimport('joomla.form.form');
		$themes = new stdClass();
		$themes->items = new stdClass();
		$themes->category = new stdClass();

		$tmpldir = $tmpldir ? $tmpldir : JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$templates = JFolder::folders($tmpldir);

		foreach ($templates as $tmpl)
		{
			// Parse & Load ITEM layout of current template
			$tmplxml = $tmpldir.DS.$tmpl.DS.'item.xml';
			if (JFile::exists($tmplxml))
			{
				// Parse the XML file
				if (FLEXI_J30GE) {
					$xml = simplexml_load_file($tmplxml);
					$document = & $xml;
				} else {
					$xml = JFactory::getXMLParser('Simple');
					$xml->loadFile($tmplxml);
					$document = & $xml->document;
				}

				$themes->items->{$tmpl} = new stdClass();
				$themes->items->{$tmpl}->name 		= $tmpl;
				$themes->items->{$tmpl}->view 		= FLEXI_ITEMVIEW;
				$themes->items->{$tmpl}->tmplvar 	= '.items.'.$tmpl;
				$themes->items->{$tmpl}->thumb		= 'components/com_flexicontent/templates/'.$tmpl.'/item.png';
				if (!FLEXI_J16GE) {
					$themes->items->{$tmpl}->params	= new JParameter('', $tmplxml);
				} else {
					// *** This can be serialized and thus Joomla Cache will work
					$themes->items->{$tmpl}->params = FLEXI_J30GE ? $document->asXML() : $document->toString();

					// *** This was moved into the template files of the forms, because JForm contains 'JXMLElement',
					// which extends the PHP built-in Class 'SimpleXMLElement', (built-in Classes cannot be serialized
					// but serialization is used by Joomla 's cache, causing problem with caching the output of this function

					//$themes->items->{$tmpl}->params		= new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
					//$themes->items->{$tmpl}->params->loadFile($tmplxml);
				}
				if (FLEXI_J30GE) {
					$themes->items->{$tmpl}->author 		= @$document->author;
					$themes->items->{$tmpl}->website 		= @$document->website;
					$themes->items->{$tmpl}->email 			= @$document->email;
					$themes->items->{$tmpl}->license 		= @$document->license;
					$themes->items->{$tmpl}->version 		= @$document->version;
					$themes->items->{$tmpl}->release 		= @$document->release;
					$themes->items->{$tmpl}->description= @$document->description;
					$groups = & $document->fieldgroups;
					$pos    = & $groups->group;
					if ($pos) {
						for ($n=0; $n<count($pos); $n++) {
							$themes->items->{$tmpl}->attributes[$n] = array();
							foreach ($pos[$n]->attributes() as $_attr_name => $_attr_val) {
								$themes->items->{$tmpl}->attributes[$n][(string)$_attr_name] = (string)$_attr_val;
							}
							$themes->items->{$tmpl}->positions[$n] = (string)$pos[$n];
						}
					}

					$css     = & $document->cssitem;
					$cssfile = & $css->file;
					if ($cssfile) {
						$themes->items->{$tmpl}->css = new stdClass();
						for ($n=0; $n<count($cssfile); $n++) {
							$themes->items->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'. (string)$cssfile[$n];
						}
					}
					$js 		= & $document->jsitem;
					$jsfile	= & $js->file;
					if ($jsfile) {
						$themes->items->{$tmpl}->js = new stdClass();
						for ($n=0; $n<count($jsfile); $n++) {
							$themes->items->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'. (string)$jsfile[$n];
						}
					}

				} else {
					$themes->items->{$tmpl}->author 		= @$document->author[0] ? $document->author[0]->data() : '';
					$themes->items->{$tmpl}->website 		= @$document->website[0] ? $document->website[0]->data() : '';
					$themes->items->{$tmpl}->email 			= @$document->email[0] ? $document->email[0]->data() : '';
					$themes->items->{$tmpl}->license 		= @$document->license[0] ? $document->license[0]->data() : '';
					$themes->items->{$tmpl}->version 		= @$document->version[0] ? $document->version[0]->data() : '';
					$themes->items->{$tmpl}->release 		= @$document->release[0] ? $document->release[0]->data() : '';
					$themes->items->{$tmpl}->description= @$document->description[0] ? $document->description[0]->data() : '';
					$groups = $document->getElementByPath('fieldgroups');
					$pos    = & $groups->group;
					if ($pos) {
						for ($n=0; $n<count($pos); $n++) {
							$themes->items->{$tmpl}->attributes[$n] = $pos[$n]->_attributes;
							$themes->items->{$tmpl}->positions[$n] = $pos[$n]->data();
						}
					}

					$css     = $document->getElementByPath('cssitem');
					$cssfile = & $css->file;
					if ($cssfile) {
						$themes->items->{$tmpl}->css = new stdClass();
						for ($n=0; $n<count($cssfile); $n++) {
							$themes->items->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$cssfile[$n]->data();
						}
					}
					$js 		= $document->getElementByPath('jsitem');
					$jsfile	=& $js->file;
					if ($jsfile) {
						$themes->items->{$tmpl}->js = new stdClass();
						for ($n=0; $n<count($jsfile); $n++) {
							$themes->items->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$jsfile[$n]->data();
						}
					}
				}
			}

			// Parse & Load CATEGORY layout of current template
			$tmplxml = $tmpldir.DS.$tmpl.DS.'category.xml';
			if (JFile::exists($tmplxml))
			{
				// Parse the XML file
				if (FLEXI_J30GE) {
					$xml = simplexml_load_file($tmplxml);
					$document = & $xml;
				} else {
					$xml = JFactory::getXMLParser('Simple');
					$xml->loadFile($tmplxml);
					$document = & $xml->document;
				}

				$themes->category->{$tmpl} = new stdClass();
				$themes->category->{$tmpl}->name 		= $tmpl;
				$themes->category->{$tmpl}->view 		= 'category';
				$themes->category->{$tmpl}->tmplvar 	= '.category.'.$tmpl;
				$themes->category->{$tmpl}->thumb		= 'components/com_flexicontent/templates/'.$tmpl.'/category.png';
				if (!FLEXI_J16GE) {
					$themes->category->{$tmpl}->params		= new JParameter('', $tmplxml);
				} else {
					// *** This can be serialized and thus Joomla Cache will work
					$themes->category->{$tmpl}->params = FLEXI_J30GE ? $document->asXML() : $document->toString();

					// *** This was moved into the template files of the forms, because JForm contains 'JXMLElement',
					// which extends the PHP built-in Class 'SimpleXMLElement', (built-in Classes cannot be serialized
					// but serialization is used by Joomla 's cache, causing problem with caching the output of this function

					//$themes->category->{$tmpl}->params		= new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => true));
					//$themes->category->{$tmpl}->params->loadFile($tmplxml);
				}
				if (FLEXI_J30GE) {
					$themes->category->{$tmpl}->author 		= @$document->author;
					$themes->category->{$tmpl}->website 	= @$document->website;
					$themes->category->{$tmpl}->email 		= @$document->email;
					$themes->category->{$tmpl}->license 	= @$document->license;
					$themes->category->{$tmpl}->version 	= @$document->version;
					$themes->category->{$tmpl}->release 	= @$document->release;
					$themes->category->{$tmpl}->description= @$document->description;
					$groups = & $document->fieldgroups;
					$pos    = & $groups->group;
					if ($pos) {
						for ($n=0; $n<count($pos); $n++) {
							$themes->category->{$tmpl}->attributes[$n] = array();
							foreach ($pos[$n]->attributes() as $_attr_name => $_attr_val) {
								$themes->category->{$tmpl}->attributes[$n][(string)$_attr_name] = (string)$_attr_val;
							}
							$themes->category->{$tmpl}->positions[$n] = (string)$pos[$n];
						}
					}
					$css     = & $document->csscategory;
					$cssfile = & $css->file;
					if ($cssfile) {
						$themes->category->{$tmpl}->css = new stdClass();
						for ($n=0; $n<count($cssfile); $n++) {
							$themes->category->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'. (string)$cssfile[$n];
						}
					}
					$js     = & $document->jscategory;
					$jsfile = & $js->file;
					if ($jsfile) {
						$themes->category->{$tmpl}->js = new stdClass();
						for ($n=0; $n<count($jsfile); $n++) {
							$themes->category->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'. (string)$jsfile[$n];
						}
					}
				} else {
					$themes->category->{$tmpl}->author 		= @$document->author[0] ? $document->author[0]->data() : '';
					$themes->category->{$tmpl}->website 	= @$document->website[0] ? $document->website[0]->data() : '';
					$themes->category->{$tmpl}->email 		= @$document->email[0] ? $document->email[0]->data() : '';
					$themes->category->{$tmpl}->license 	= @$document->license[0] ? $document->license[0]->data() : '';
					$themes->category->{$tmpl}->version 	= @$document->version[0] ? $document->version[0]->data() : '';
					$themes->category->{$tmpl}->release 	= @$document->release[0] ? $document->release[0]->data() : '';
					$themes->category->{$tmpl}->description = @$document->description[0] ? $document->description[0]->data() : '';
					$groups = $document->getElementByPath('fieldgroups');
					$pos    = & $groups->group;
					if ($pos) {
						for ($n=0; $n<count($pos); $n++) {
							$themes->category->{$tmpl}->attributes[$n] = $pos[$n]->_attributes;
							$themes->category->{$tmpl}->positions[$n] = $pos[$n]->data();
						}
					}
					$css     = $document->getElementByPath('csscategory');
					$cssfile = & $css->file;
					if ($cssfile) {
						$themes->category->{$tmpl}->css = new stdClass();
						for ($n=0; $n<count($cssfile); $n++) {
							$themes->category->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$cssfile[$n]->data();
						}
					}
					$js     = $document->getElementByPath('jscategory');
					$jsfile = & $js->file;
					if ($jsfile) {
						$themes->category->{$tmpl}->js = new stdClass();
						for ($n=0; $n<count($jsfile); $n++) {
							$themes->category->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$jsfile[$n]->data();
						}
					}
				}

			}
		}
		return $themes;
	}

	static function getTemplates($lang_files = 'all')
	{
		$flexiparams = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $flexiparams->get('print_logging_info');

		// Log content plugin and other performance information
		if ($print_logging_info) { global $fc_run_times; $start_microtime = microtime(true); }

		if ( !FLEXI_J30GE ) {  // && FLEXI_CACHE  ,  Ignore cache settings since XML parsing in J1.5/J2.5 is costly
			// add the templates to templates cache
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');
			$tmplcache->setCaching(1);         // Force cache ON
			$tmplcache->setLifeTime(24*3600);  // Set expire time (hard-code this to 1 day), since it is costly
			$tmpls = $tmplcache->call(array('flexicontent_tmpl', 'parseTemplates'));
			$cached = 1;
		}
		else {
			$tmpls = flexicontent_tmpl::parseTemplates();
			$cached = 0;
		}

		// Load Template-Specific language file(s) to override or add new language strings
		if (FLEXI_FISH || FLEXI_J16GE) {
			if ( $lang_files == 'all' ) foreach ($tmpls->category as $tmpl => $d) FLEXIUtilities::loadTemplateLanguageFile( $tmpl );
			else if ( is_array($lang_files) )  foreach ($lang_files as $tmpl) FLEXIUtilities::loadTemplateLanguageFile( $tmpl );
			else if ( is_string($lang_files) && $load_lang ) FLEXIUtilities::loadTemplateLanguageFile( $lang_files );
		}

		if ($print_logging_info) $fc_run_times[$cached ? 'templates_parsing_cached' : 'templates_parsing_noncached'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		return $tmpls;
	}

	static function getThemes($tmpldir='')
	{
		jimport('joomla.filesystem.folder');

		$tmpldir = $tmpldir?$tmpldir:JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$themes = JFolder::folders($tmpldir);

		return $themes;
	}

	/**
	 * Method to get all available fields for a template in a view
	 *
	 * @access public
	 * @return object
	 */
	static function getFieldsByPositions($folder, $type) {
		if ($type=='item') $type='items';

		static $templates;
		if(!isset($templates[$folder])) {
			$templates[$folder] = array();
		}
		if(!isset($templates[$folder][$type])) {
			$db = JFactory::getDBO();
			$query  = 'SELECT *'
					. ' FROM #__flexicontent_templates'
					. ' WHERE template = ' . $db->Quote($folder)
					. ' AND layout = ' . $db->Quote($type)
					;
			$db->setQuery($query);
			$positions = $db->loadObjectList('position');
			foreach ($positions as $pos) {
				$pos->fields = explode(',', $pos->fields);
			}
			$templates[$folder][$type] = & $positions;
		}
		return $templates[$folder][$type];
	}
}

class flexicontent_images
{
	/**
	 * Get file size and icons
	 *
	 * @since 1.5
	 */
	static function BuildIcons($rows)
	{
		jimport('joomla.filesystem.file');

		for ($i=0, $n=count($rows); $i < $n; $i++) {

			$basePath = $rows[$i]->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;

			if (is_file($basePath.DS.$rows[$i]->filename)) {
				$path = str_replace(DS, '/', JPath::clean($basePath.DS.$rows[$i]->filename));

				$size = filesize($path);

				if ($size < 1024) {
					$rows[$i]->size = $size . ' bytes';
				} else {
					if ($size >= 1024 && $size < 1024 * 1024) {
						$rows[$i]->size = sprintf('%01.2f', $size / 1024.0) . ' Kb';
					} else {
						$rows[$i]->size = sprintf('%01.2f', $size / (1024.0 * 1024)) . ' Mb';
					}
				}
			} else {
				$rows[$i]->size = 'N/A';
			}

			if ($rows[$i]->url == 1)
			{
				$ext = $rows[$i]->ext;
			} else {
				$ext = strtolower(JFile::getExt($rows[$i]->filename));
			}
			switch ($ext)
			{
				// Image
				case 'jpg':
				case 'png':
				case 'gif':
				case 'xcf':
				case 'odg':
				case 'bmp':
				case 'jpeg':
					$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/image.png';
					break;

				// Non-image document
				default:
					$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$ext.'.png';
					if (file_exists($icon)) {
						$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/'.$ext.'.png';
					} else {
						$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
					}
					break;
			}

		}

		return $rows;
	}

}


class FLEXIUtilities
{
	static function funcIsDisabled($function)
	{
		static $disabledFuncs = null;
		$func = strtolower($function);
		if ($disabledFuncs !== null) return isset($disabledFuncs[$func]);
		
		$disabledFuncs = array();
		$disable_local  = explode(',',     strtolower(@ini_get('disable_functions')));
		$disable_global = explode(',', strtolower(@get_cfg_var('disable_functions')));
		
		foreach ($disable_local as $key => $value) {
			$disabledFuncs[trim($value)] = 'local';
		}
		foreach ($disable_global as $key => $value) {
			$disabledFuncs[trim($value)] = 'global';
		}
		if (@ini_get('safe_mode')) {
			$disabledFuncs['shell_exec']     = 'local';
			$disabledFuncs['set_time_limit'] = 'local';
		}
		
		return isset($disabledFuncs[$func]);
	}
	
	
	/**
	 * Load Template-Specific language file to override or add new language strings
	 *
	 * @return object
	 * @since 1.5
	 */

	static function loadTemplateLanguageFile( $tmplname='default', $view='' )
	{
		// Check that template name was given
		$tmplname = empty($tmplname) ? 'default' : $tmplname;

		// This is normally component/module/plugin name, we could use 'category', 'items', etc to have a view specific language file
		// e.g. en/en.category.ini, but this is an overkill and make result into duplication of strings ... better all in one file
		$extension = '';  // JRequest::get('view');

		// Current language, we decided to use LL-CC (language-country) format mapping SEF shortcode, e.g. 'en' to 'en-GB'
		$user_lang = flexicontent_html::getUserCurrentLang();
		$languages = FLEXIUtilities::getLanguages($hash='shortcode');
		if ( !$user_lang || !isset($languages->$user_lang->code) ) return;  // Language has been disabled
		$language_tag = $languages->$user_lang->code;

		// We will use template folder as BASE of language files instead of joomla's language folder
		// Since FLEXIcontent templates are meant to be user-editable it makes sense to place language files inside them
		$base_dir = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$tmplname;

		// Final use joomla's API to load our template's language files -- (load english template language file then override with current language file)
		JFactory::getLanguage()->load($extension, $base_dir, 'en-GB', $reload=true);        // Fallback to english language template file
		JFactory::getLanguage()->load($extension, $base_dir, $language_tag, $reload=true);  // User's current language template file
	}

	/**
	 * Method to get information of site languages
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getlanguageslist($published_only=false, $add_all = true)
	{
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		static $pub_languages = null;
		static $all_languages = null;
		
		if ( $published_only ) {
			if ($pub_languages) return $pub_languages;
			else $pub_languages = false;
		}
		
		if ( !$published_only ) {
			if ($all_languages) return $all_languages;
			else $all_languages = false;
		}

		// ******************
		// Retrieve languages
		// ******************
		if (FLEXI_J16GE) {   // Use J1.6+ language info
			$query = 'SELECT DISTINCT lc.lang_id as id, lc.image as image_prefix, lc.lang_code as code, lc.title_native, '
				//. ' CASE WHEN CHAR_LENGTH(lc.title_native) THEN CONCAT(lc.title, " (", lc.title_native, ")") ELSE lc.title END as name '
				. ' lc.title as name '
				.' FROM #__languages as lc '
				.' WHERE 1 '.($published_only ? ' AND lc.published=1' : '')
				. ' ORDER BY lc.ordering ASC '
				;
		} else if (FLEXI_FISH) {   // Use joomfish languages table
			$query = 'SELECT l.* '
				. ( FLEXI_FISH_22GE ? ', lext.* ' : '' )
				. ( FLEXI_FISH_22GE ? ', l.lang_id as id ' : ', l.id ' )
				. ( FLEXI_FISH_22GE ? ', l.lang_code as code, l.sef as shortcode' : ', l.code, l.shortcode' )
				. ( FLEXI_FISH_22GE ? ', CASE WHEN CHAR_LENGTH(l.title_native) THEN CONCAT(l.title, " (", l.title_native, ")") ELSE l.title END as name ' : ', l.name ' )
				. ' FROM #__languages as l'
				. ( FLEXI_FISH_22GE ? ' LEFT JOIN #__jf_languages_ext as lext ON l.lang_id=lext.lang_id ' : '')
				. ' WHERE '.    (FLEXI_FISH_22GE ? ' l.published=1 ' : ' l.active=1 ')
				. ' ORDER BY '. (FLEXI_FISH_22GE ? ' lext.ordering ASC ' : ' l.ordering ASC ')
				;
		} else {
			//echo "<pre>"; debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS); echo "</pre>";
			//JError::raiseNotice(500, 'getlanguageslist(): Notice no joomfish installed');
			//return array();
		}
		if ( !empty($query) ) {
			$db->setQuery($query);
			$languages = $db->loadObjectList('id');
			//echo "<pre>"; print_r($languages); echo "</pre>"; exit;
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		}
		
		
		// *********************
		// Calculate image paths
		// *********************
		if (FLEXI_J16GE)  {  // FLEXI_J16GE, use J1.6+ images
			$imgpath	= $app->isAdmin() ? '../images/':'images/';
			$mediapath	= $app->isAdmin() ? '../media/mod_languages/images/' : 'media/mod_languages/images/';
		} else {      // Use joomfish images
			$imgpath	= $app->isAdmin() ? '../images/':'images/';
			$mediapath	= $app->isAdmin() ? '../components/com_joomfish/images/flags/' : 'components/com_joomfish/images/flags/';
		}
		
		
		// ************************
		// Prepare language objects
		// ************************
		$_languages = array();
		
		// J1.6+ add 'ALL' also add 'ALL' if no languages found, since this is default for J1.6+
		if (FLEXI_J16GE && $add_all) {
			$lang_all = new stdClass();
			$lang_all->code = '*';
			$lang_all->name = JText::_('FLEXI_ALL');
			$lang_all->shortcode = '*';
			$lang_all->id = 0;
			$_languages = array( 0 => $lang_all);
		}
		
		// J1.5 add default site language if no languages found, e.g. no Joom!Fish installed
		if (!FLEXI_J16GE && empty($languages)) {
			$lang_default = new stdClass();
			$lang_default->code = flexicontent_html::getSiteDefaultLang();
			$lang_default->name = $lang_default->code;
			$lang_default->shortcode = strpos($lang_default->code,'-') ?
				substr($lang_default->code, 0, strpos($lang_default->code,'-')) :
				$lang_default->code;
			$lang_default->id = 0;
			$_languages = array( 0 => $lang_default);
		}
		
		// Check if no languages found and return
		if ( empty($languages) )  return $_languages;
		
		if (FLEXI_J16GE)  // FLEXI_J16GE, based on J1.6+ language data and images
		{
			foreach ($languages as $lang) {
				// Calculate/Fix languages data
				$lang->shortcode = strpos($lang->code,'-') ?
					substr($lang->code, 0, strpos($lang->code,'-')) :
					$lang->code;
				//$lang->id = $lang->extension_id;
				$image_prefix = $lang->image_prefix ? $lang->image_prefix : $lang->shortcode;
				// $lang->image, holds a custom image path
				$lang->imgsrc = @$lang->image ? $imgpath . $lang->image : $mediapath . $image_prefix . '.gif';
				$_languages[$lang->id] = $lang;
			}

			// Also prepend '*' (ALL) language to language array
			//echo "<pre>"; print_r($languages); echo "</pre>"; exit;

			// Select language -ALL- if none selected
			//$selected = $selected ? $selected : '*';    // WRONG behavior commented out
		}
		else if (FLEXI_FISH_22GE)  // JoomFish v2.2+
		{
			require_once(JPATH_ROOT.DS.'administrator'.DS.'components'.DS.'com_joomfish'.DS.'helpers'.DS.'extensionHelper.php' );
			foreach ($languages as $lang) {
				// Get image path via helper function
				$_imgsrc = JoomfishExtensionHelper::getLanguageImageSource($lang);
				$lang->imgsrc = JURI::root(true).($_imgsrc[0]!='/' ? '/' : '').$_imgsrc;
				$_languages[$lang->id] = $lang;
			}
		}
		else      // JoomFish until v2.1
		{
			foreach ($languages as $lang) {
				// $lang->image, holds a custom image path
				$lang->imgsrc = @$lang->image ? $imgpath . $lang->image : $mediapath . $lang->shortcode . '.gif';
				$_languages[$lang->id] = $lang;
			}
		}
		$languages = $_languages;
		
		if ( $published_only ) {
			$pub_languages = $_languages;
		} else {
			$all_languages = $_languages;
		}
		return $_languages;
	}
	
	
	/**
	 * Method to build an array of languages hashed by id or by language code
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getLanguages($hash='code', $published_only=false)
	{
		static $langs = array();
		static $languages;
		
		if (isset($langs[$hash])) return $langs[$hash];
		if (!$languages) $languages = FLEXIUtilities::getlanguageslist($published_only);
		
		$langs[$hash] = new stdClass();
		foreach ($languages as $language) {
			$langs[$hash]->{$language->$hash} = $language;
		}

		return $langs[$hash];
	}


	/**
	 * Method to get the last version kept
	 *
	 * @return int
	 * @since 1.5
	 */
	static function &getLastVersions ($id=NULL, $justvalue=false, $force=false)
	{
		static $g_lastversions = NULL;
		static $all_retrieved  = false;

		if(
			$g_lastversions===NULL || $force ||
			($id && !isset($g_lastversions[$id])) ||
			(!$id && !$all_retrieved)
		) {
			if (!$id) $all_retrieved = true;
			$g_lastversions =  array();
			$db = JFactory::getDBO();
			$query = "SELECT item_id as id, max(version_id) as version"
									." FROM #__flexicontent_versions"
									." WHERE 1"
									.($id ? " AND item_id=".(int)$id : "")
									." GROUP BY item_id";
			$db->setQuery($query);
			$rows = $db->loadAssocList('id');
			foreach($rows as $row_id => $row) {
				$g_lastversions[$row_id] = $row;
			}
			unset($rows);
		}

		// Special case (version number of new item): return version zero
		if (!$id && $justvalue) { $v = 0; return $v; }

		// an item id was given return item specific data
		if ($id) {
			$return = $justvalue ? @$g_lastversions[$id]['version'] : @$g_lastversions[$id];
			return $return;
		}

		// no item id was given return all version data
		return $g_lastversions;
	}


	static function &getCurrentVersions ($id=NULL, $justvalue=false, $force=false)
	{
		static $g_currentversions;  // cache ...

		if( $g_currentversions==NULL || $force )
		{
			$db = JFactory::getDBO();
			if (!FLEXI_J16GE) {
				$query = "SELECT i.id, i.version FROM #__content AS i"
					." WHERE i.sectionid=".FLEXI_SECTION
					. ($id ? " AND i.id=".(int)$id : "")
					;
			} else {
				$query = "SELECT i.id, i.version FROM #__content as i"
						. " JOIN #__categories AS c ON i.catid=c.id"
						. " WHERE c.extension='".FLEXI_CAT_EXTENSION."'"
						. ($id ? " AND i.id=".(int)$id : "")
						;
			}
			$db->setQuery($query);
			$rows = $db->loadAssocList();
			$g_currentversions = array();
			foreach($rows as $row) {
				$g_currentversions[$row["id"]] = $row;
			}
			unset($rows);
		}

		// Special case (version number of new item): return version zero
		if (!$id && $justvalue) { $v = 0; return $v; }

		// an item id was given return item specific data
		if($id) {
			$return = $justvalue ? @$g_currentversions[$id]['version'] : @$g_currentversions[$id];
			return $return;
		}

		// no item id was given return all version data
		return $g_currentversions;
	}


	static function &getLastItemVersion($id)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT max(version) as version'
				.' FROM #__flexicontent_items_versions'
				.' WHERE item_id = ' . (int)$id
				;
		$db->setQuery($query, 0, 1);
		$lastversion = $db->loadResult();

		return (int)$lastversion;
	}


	static function &currentMissing()
	{
		static $status;
		if(!$status) {
			$db = JFactory::getDBO();
			$query = "SELECT c.id,c.version,iv.version as iversion FROM #__content as c "
				." LEFT JOIN #__flexicontent_items_versions as iv ON c.id=iv.item_id AND c.version=iv.version"
				.(FLEXI_J16GE ? " JOIN #__categories as cat ON c.catid=cat.id" : "")
				." WHERE c.version > '1' AND iv.version IS NULL"
				.(!FLEXI_J16GE ? " AND sectionid='".FLEXI_SECTION."'" : " AND cat.extension='".FLEXI_CAT_EXTENSION."'")
				." LIMIT 0,1";
			$db->setQuery($query);
			$rows = $db->loadObjectList("id");
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');

			$rows = is_array($rows) ? $rows : array();
			$status = false;
			if(count($rows)>0) {
				$status = true;
			}
			unset($rows);
		}
		return $status;
	}


	/**
	 * Method to get the first version kept
	 *
	 * @return int
	 * @since 1.5
	 */
	static function &getFirstVersion($id, $max, $current_version)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT version_id'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$id
				.' AND version_id!=' . (int)$current_version
				.' ORDER BY version_id DESC'
				;
		$db->setQuery($query, ($max-1), 1);
		$firstversion = (int)$db->loadResult();  // return zero if no version is found
		return $firstversion;
	}


	/**
	 * Method to get the versions count
	 *
	 * @return int
	 * @since 1.5
	 */
	static function &getVersionsCount($id)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT COUNT(*)'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$id
				;
		$db->setQuery($query);
		$versionscount = $db->loadResult();

		return $versionscount;
	}


	static function doPlgAct()
	{
		$plg = JRequest::getVar('plg');
		$act = JRequest::getVar('act');
		if($plg && $act) {
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($plg);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.$plgfolder.DS.strtolower($plg).'.php';
			if(file_exists($path)) require_once($path);
			$class = "plgFlexicontent_fields{$plg}";
			if(class_exists($class) && in_array($act, get_class_methods($class))) {
				//call_user_func("$class::$act");
				call_user_func(array($class, $act));
			}
		}
	}


	static function getCache($group='', $client=0)
	{
		$conf = JFactory::getConfig();
		//$client = 0;//0 is site, 1 is admin
		$options = array(
			'defaultgroup'	=> $group,
			'storage' 		=> $conf->get('cache_handler', ''),
			'caching'		=> true,
			'cachebase'		=> ($client == 1) ? JPATH_ADMINISTRATOR . '/cache' : $conf->get('cache_path', JPATH_SITE . '/cache')
		);

		jimport('joomla.cache.cache');
		$cache = JCache::getInstance('', $options);
		return $cache;
	}


	static function call_FC_Field_Func( $fieldtype, $func, $args=null )
	{
		static $fc_plgs;

		if ( !isset( $fc_plgs[$fieldtype] ) ) {
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($fieldtype);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.$plgfolder.DS.strtolower($fieldtype).'.php';
			if(file_exists($path)) require_once($path);
			else {
				JFactory::getApplication()->enqueueMessage(nl2br("While calling field method: $func(): cann find field type: $fieldtype. This is internal error or wrong field name"),'error');
				return;
			}

			// 2. Create plugin instance
			$class = "plgFlexicontent_fields{$fieldtype}";
			if( class_exists($class) ) {
				// Create class name of the plugin
				$className = 'plg'.'flexicontent_fields'.$fieldtype;
				// Create a plugin instance
				$dispatcher = JDispatcher::getInstance();
				$fc_plgs[$fieldtype] =  new $className($dispatcher, array());
				// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters), CHECKING if parameters exist
				$plugin_db_data = JPluginHelper::getPlugin('flexicontent_fields',$fieldtype);
				$fc_plgs[$fieldtype]->params = FLEXI_J16GE ? new JRegistry( @$plugin_db_data->params ) : new JParameter( @$plugin_db_data->params );
			} else {
				JFactory::getApplication()->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct field name"),'error');
				return;
			}
		}

		// 3. Execute only if it exists
		if (!$func) return;
		$class = "plgFlexicontent_fields{$fieldtype}";
		if(in_array($func, get_class_methods($class))) {
			return call_user_func_array(array($fc_plgs[$fieldtype], $func), $args);
		}
	}


	/* !!! FUNCTION NOT DONE YET */
	static function call_Content_Plg_Func( $plgname, $func, $args=null )
	{
		static $content_plgs;

		if ( !isset( $content_plgs[$plgname] ) ) {
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($plgname);
			$path = JPATH_ROOT.DS.'plugins'.DS.'content'.$plgfolder.DS.strtolower($plgname).'.php';
			if(file_exists($path)) require_once($path);
			else {
				JFactory::getApplication()->enqueueMessage(nl2br("Cannot load CONTENT Plugin: $plgname\n Plugin may have been uninistalled"),'error');
				return;
			}

			// 2. Create plugin instance
			$class = "plgContent{$plgname}";
			if( class_exists($class) ) {
				// Create class name of the plugin
				$className = 'plg'.'content'.$plgname;
				// Create a plugin instance
				$dispatcher = JDispatcher::getInstance();
				$content_plgs[$plgname] =  new $className($dispatcher, array());
				// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters)
				$plugin_db_data = JPluginHelper::getPlugin('content',$plgname);
				$content_plgs[$plgname]->params = FLEXI_J16GE ? new JRegistry( @$plugin_db_data->params ) : new JParameter( @$plugin_db_data->params );
			} else {
				JFactory::getApplication()->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct field name"),'error');
				return;
			}
		}

		// 3. Execute only if it exists
		$class = "plgContent{$plgname}";
		if(in_array($func, get_class_methods($class))) {
			return call_user_func_array(array($content_plgs[$plgname], $func), $args);
		}
	}


	/**
	 * Return unicode char by its code
	 * Credits: ?
	 *
	 * @param int $dec
	 * @return utf8 char
	 */
	static function unichr($dec) {
	  if ($dec < 128) {
	    $utf = chr($dec);
	  } else if ($dec < 2048) {
	    $utf = chr(192 + (($dec - ($dec % 64)) / 64));
	    $utf .= chr(128 + ($dec % 64));
	  } else {
	    $utf = chr(224 + (($dec - ($dec % 4096)) / 4096));
	    $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
	    $utf .= chr(128 + ($dec % 64));
	  }
	  return $utf;
	}


	/**
	 * Return unicode code of a utf8 char
	 * Credits: ?
	 *
	 * @param int $c
	 * @return utf8 ord
	 */
	static function uniord($c) {
		$h = ord($c{0});
		if ($h <= 0x7F) {
			return $h;
		} else if ($h < 0xC2) {
			return false;
		} else if ($h <= 0xDF) {
			return ($h & 0x1F) << 6 | (ord($c{1}) & 0x3F);
		} else if ($h <= 0xEF) {
			return ($h & 0x0F) << 12 | (ord($c{1}) & 0x3F) << 6
			| (ord($c{2}) & 0x3F);
		} else if ($h <= 0xF4) {
			return ($h & 0x0F) << 18 | (ord($c{1}) & 0x3F) << 12
			| (ord($c{2}) & 0x3F) << 6
			| (ord($c{3}) & 0x3F);
		} else {
			return false;
		}
	}


	/**
	 * Return unicode string when giving an array of utf8 ords
	 * Credits: Darien Hager
	 *
	 * @param  $ords   utf8 ord arrray
	 * @return $str    utf8 string
	 */
	static function ords_to_unistr($ords, $encoding = 'UTF-8'){
		// Turns an array of ordinal values into a string of unicode characters
		$str = '';
		for($i = 0; $i < sizeof($ords); $i++){
			// Pack this number into a 4-byte string
			// (Or multiple one-byte strings, depending on context.)
			$v = $ords[$i];
			$str .= pack("N",$v);
		}
		$str = mb_convert_encoding($str,$encoding,"UCS-4BE");
		return($str);
	}


	/**
	 * Return unicode string when giving an array of utf8 ords
	 * Credits: Darien Hager
	 *
	 * @param  $str    utf8 string
	 * @return $ords   utf8 ord arrray
	 */
	static function unistr_to_ords($str, $encoding = 'UTF-8')
	{
		// Turns a string of unicode characters into an array of ordinal values,
		// Even if some of those characters are multibyte.
		$str = mb_convert_encoding($str,"UCS-4BE",$encoding);
		$ords = array();

		// Visit each unicode character
		//for($i = 0; $i < mb_strlen($str,"UCS-4BE"); $i++){
		//for($i = 0; $i < utf8_strlen($str); $i++){
		for($i = 0; $i < JString::strlen($str,"UCS-4BE"); $i++){
			// Now we have 4 bytes. Find their total
			// numeric value.
			$s2 = JString::substr($str,$i,1,"UCS-4BE");
			$val = unpack("N",$s2);
			$ords[] = $val[1];
		}
		return($ords);
	}
	

	/*
	 * Method to confirm if a given string is a valid MySQL date
	 * param  string			$date
	 * return boolean			true if valid date, false otherwise
	 */
	static function isSqlValidDate($date)
	{
		$db = JFactory::getDBO();
		$q = "SELECT day(".$db->Quote($date).")";
		$db->setQuery($q);
		$num = $db->loadResult();
		$valid = $num > 0;
		return $valid;
	}

	/*
	 * Converts a string (containing a csv file) into a array of records ( [row][col] )and returns it
	 * @author: Klemen Nagode (in http://stackoverflow.com/)
	 */
	static function csvstring_to_array($string, $field_separator = ',', $enclosure_char = '"', $record_separator = "\n")
	{
		$array = array();   // [row][cols]
		$size = strlen($string);
		$columnIndex = 0;
		$rowIndex = 0;
		$fieldValue="";
		$isEnclosured = false;
		// Field separator
		$fld_sep_start = $field_separator{0};
		$fld_sep_size  = strlen( $field_separator );
		// Record (item) separator
		$rec_sep_start = $record_separator{0};
		$rec_sep_size  = strlen( $record_separator );

		for($i=0; $i<$size;$i++)
		{
			$char = $string{$i};
			$addChar = "";

			if($isEnclosured) {
				if($char==$enclosure_char) {
					if($i+1<$size && $string{$i+1}==$enclosure_char) {
						// escaped char
						$addChar=$char;
						$i++; // dont check next char
					} else {
						$isEnclosured = false;
					}
				} else {
					$addChar=$char;
				}
			}
			else
			{
				if($char==$enclosure_char) {
					$isEnclosured = true;
				} else {
					if( $char==$fld_sep_start && $i+$fld_sep_size < $size && substr($string, $i,$fld_sep_size) == $field_separator ) {
						$i = $i + ($fld_sep_size-1);
						$array[$rowIndex][$columnIndex] = $fieldValue;
						$fieldValue="";

						$columnIndex++;
					} else if( $char==$rec_sep_start && $i+$rec_sep_size < $size && substr($string, $i,$rec_sep_size) == $record_separator ) {
						$i = $i + ($rec_sep_size-1);
						echo "\n";
						$array[$rowIndex][$columnIndex] = $fieldValue;
						$fieldValue="";
						$columnIndex=0;
						$rowIndex++;
					} else {
						$addChar=$char;
					}
				}
			}
			if($addChar!="") {
				$fieldValue.=$addChar;
			}
		}

		if($fieldValue) { // save last field
			$array[$rowIndex][$columnIndex] = $fieldValue;
		}
		return $array;
	}

	/**
	 * Helper method to format a parameter value as array
	 *
	 * @return object
	 * @since 1.5
	 */
	static function paramToArray($value, $regex = "", $filterfunc = "")
	{
		if ($regex) {
			$value = trim($value);
			$value = !$value  ?  array()  :  preg_split($regex, $value);
		}
		if ($filterfunc) {
			array_map($filterfunc, $value);
		}

		if (FLEXI_J16GE && !is_array($value)) {
			$value = explode("|", $value);
			$value = ($value[0]=='') ? array() : $value;
		} else {
			$value = !is_array($value) ? array($value) : $value;
		}
		return $value;
	}

	/**
	 * Suppresses given plugins (= prevents them from triggering)
	 *
	 * @return void
	 * @since 1.5
	 */
	static function suppressPlugins( $name_arr, $action ) {
		static $plgs = array();

		foreach	($name_arr as $name)
		{
			if (!isset($plgs[$name])) {
				JPluginHelper::importPlugin('content', $name);
				$plgs[$name] = JPluginHelper::getPlugin('content', $name);
			}
			if ($plgs[$name] && $action=='suppress') {
				$plgs[$name]->type = '_suppress';
			}
			if ($plgs[$name] && $action=='restore') {
				$plgs[$name]->type = 'content';
			}
		}
	}
}


/*
 * CLASS with common methods for handling interaction with DB
 */
class flexicontent_db
{
	/*
	 * Retrieve author/user configuration
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getUserConfig($user_id)
	{
		$db = JFactory::getDBO();
		$db->setQuery('SELECT author_basicparams FROM #__flexicontent_authors_ext WHERE user_id = ' . $user_id);
		if ( $authorparams = $db->loadResult() )
			$authorparams = FLEXI_J16GE ? new JRegistry($authorparams) : new JParameter($authorparams);
		
		return $authorparams;
	}
	
	
	/*
	 * Find stopwords and too small words
	 *
	 * @return array
	 * @since 1.5
	 */
	static function removeInvalidWords($words, &$stopwords, &$shortwords, $tbl='flexicontent_items_ext', $col='search_index', $isprefix=1) {
		$db     = JFactory::getDBO();
		$app    = JFactory::getApplication();
		$option = JRequest::getVar('option');
		$min_word_len = $app->getUserState( $option.'.min_word_len', 0 );
		
		$_word_clause = $isprefix ? '+%s*' : '+%s';
		$query = 'SELECT '.$col
			.' FROM #__'.$tbl
			.' WHERE MATCH ('.$col.') AGAINST ("'.$_word_clause.'" IN BOOLEAN MODE)'
			.' LIMIT 1';
		$_words = array();
		foreach ($words as $word) {
			$quoted_word = FLEXI_J16GE ? $db->escape($word, true) : $db->getEscaped($word, true);
			$q = sprintf($query, $quoted_word);
			$db->setQuery($q);
			$result = $db->loadAssocList();
			if ( !empty($result) ) {
				$_words[] = $word;      // word found
			} else if ( mb_strlen($word) < $min_word_len ) {
				$shortwords[] = $word;  // word not found and word too short
			} else {
				$stopwords[] = $word;   // word not found
			}
		}
		return $_words;
	}
	
	/**
	 * Helper method to execute an SQL file containing multiple queries
	 *
	 * @return object
	 * @since 1.5
	 */
	static function execute_sql_file($sql_file)
	{
		$queries = file_get_contents( $sql_file );
		$queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $queries);
		
		$db = JFactory::getDBO();
		foreach ($queries as $query) {
			$query = trim($query);
			if (!$query) continue;
			
			$db->setQuery($query);
			$result = $db->query();
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		}
	}
	
	
	/**
	 * Helper method to execute a query directly, bypassing Joomla DB Layer
	 *
	 * @return object
	 * @since 1.5
	 */
	static function & directQuery($query, $assoc = false, $unbuffered = false)
	{
		$db     = JFactory::getDBO();
		$app = JFactory::getApplication();
		$dbprefix = $app->getCfg('dbprefix');
		$dbtype   = $app->getCfg('dbtype');

		if (FLEXI_J16GE) {
			$query = $db->replacePrefix($query);
			$db_connection = $db->getConnection();
		} else {
			$query = str_replace("#__", $dbprefix, $query);
			$db_connection = $db->_resource;
		}
		//echo "<pre>"; print_r($query); echo "\n\n";
		
		$data = array();
		if ($dbtype == 'mysqli') {
			$result = $unbuffered ?
				mysqli_query( $db_connection , $query, MYSQLI_USE_RESULT ) :
				mysqli_query( $db_connection , $query ) ;
			if ($result===false)
				throw new Exception('error '.__FUNCTION__.'():: '.mysqli_error($db_connection));
			
			if ($assoc) {
				while($row = mysqli_fetch_assoc($result)) $data[] = $row;
			} else {
				while($row = mysqli_fetch_object($result)) $data[] = $row;
			}
			mysqli_free_result($result);
		}
		
		else if ($dbtype == 'mysql') {
			$result = $unbuffered ?
				mysql_unbuffered_query( $query, $db_connection ) :
				mysql_query( $query, $db_connection  ) ;
			
			if ($result===false)
				throw new Exception('error '.__FUNCTION__.'():: '.mysql_error($db_connection));
				
			if ($assoc) {
				while($row = mysql_fetch_assoc($result)) $data[] = $row;
			} else {
				while($row = mysql_fetch_object($result)) $data[] = $row;
			}
			mysql_free_result($result);
		}
		
		else {
			throw new Exception( __FUNCTION__.'(): direct db query, unsupported DB TYPE' );
		}

		return $data;
	}


	/**
	 * Build the order clause of item listings
	 * precedence: $request_var ==> $order ==> $config_param ==> $default_order_col (& $default_order_dir)
	 * @access private
	 * @return string
	 */
	static function buildItemOrderBy(&$params=null, &$order='', $request_var='orderby', $config_param='orderby', $i_as='i', $rel_as='rel', $default_order_col_1st='', $default_order_dir_1st='', $sfx='', $support_2nd_lvl=false)
	{
		// Use global params ordering if parameters were not given
		if (!$params) $params = JComponentHelper::getParams( 'com_flexicontent' );
		
		$order_fallback = 'rdate';  // Use as default or when an invalid ordering is requested
		$orderbycustomfield   = (int) $params->get('orderbycustomfield'.$sfx, 1);    // Backwards compatibility, defaults to enabled *
		$orderbycustomfieldid = (int) $params->get('orderbycustomfieldid'.$sfx, 0);  // * but this needs to be set in order for field ordering to be used
		
		// 1. If a FORCED -ORDER- is not given, then use ordering parameters from configuration. NOTE: custom field ordering takes priority
		if (!$order) {
			$order = ($orderbycustomfield && $orderbycustomfieldid)  ?  'field'  :  $params->get($config_param.$sfx, $order_fallback);
		}
		
		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$order = $params->get('orderby_override') && ($request_order = JRequest::getVar($request_var.$sfx)) ? $request_order : $order;
		
		// 3. Check various cases of invalid order, print warning, and reset ordering to default
		if ($order=='field' && !$orderbycustomfieldid ) {
			// This can occur only if field ordering was requested explicitly, otherwise an not set 'orderbycustomfieldid' will prevent 'field' ordering
			echo "Custom field ordering was selected, but no custom field is selected to be used for ordering<br/>";
			$order = $order_fallback;
		}
		if ($order=='commented') {
			if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) {
				echo "jcomments not installed, you need jcomments to use 'Most commented' ordering OR display comments information.<br>\n";
				$order = $order_fallback;
			} 
		}
		
		$order_col_1st = $default_order_col_1st;
		$order_dir_1st = $default_order_dir_1st;
		flexicontent_db::_getOrderByClause($params, $order, $i_as, $rel_as, $order_col_1st, $order_dir_1st, $sfx);
		$order_arr[1] = $order;
		$orderby = ' ORDER BY '.$order_col_1st.' '.$order_dir_1st;
		
		
		// ****************************************************************
		// 2nd level ordering, (currently only supported when no SFX given)
		// ****************************************************************
		
		if ($sfx!='' || !$support_2nd_lvl) {
			$orderby .= $order_col_1st != $i_as.'.title'  ?  ', '.$i_as.'.title'  :  '';
			$order_arr[2] = '';
			$order = $order_arr;
			return $orderby;
		}
		
		$order = '';  // Clear this, thus force retrieval from parameters (below)
		$sfx='_2nd';  // Set suffix of second level ordering
		$order_fallback = 'alpha';  // Use as default or when an invalid ordering is requested
		$orderbycustomfield   = (int) $params->get('orderbycustomfield'.$sfx, 1);    // Backwards compatibility, defaults to enabled *
		$orderbycustomfieldid = (int) $params->get('orderbycustomfieldid'.$sfx, 0);  // * but this needs to be set in order for field ordering to be used
		
		// 1. If a FORCED -ORDER- is not given, then use ordering parameters from configuration. NOTE: custom field ordering takes priority
		if (!$order) {
			$order = ($orderbycustomfield && $orderbycustomfieldid)  ?  'field'  :  $params->get($config_param.$sfx, $order_fallback);
		}
		
		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$order = $request_var && ($request_order = JRequest::getVar($request_var.$sfx)) ? $request_order : $order;
		
		// 3. Check various cases of invalid order, print warning, and reset ordering to default
		if ($order=='field' && !$orderbycustomfieldid ) {
			// This can occur only if field ordering was requested explicitly, otherwise an not set 'orderbycustomfieldid' will prevent 'field' ordering
			echo "Custom field ordering was selected, but no custom field is selected to be used for ordering<br/>";
			$order = $order_fallback;
		}
		if ($order=='commented') {
			if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) {
				echo "jcomments not installed, you need jcomments to use 'Most commented' ordering OR display comments information.<br>\n";
				$order = $order_fallback;
			} 
		}
		
		$order_col_2nd = '';
		$order_dir_2nd = '';
		if ($order!='default') {
			flexicontent_db::_getOrderByClause($params, $order, $i_as, $rel_as, $order_col_2nd, $order_dir_2nd, $sfx);
			$order_arr[2] = $order;
			$orderby .= ', '.$order_col_2nd.' '.$order_dir_2nd;
		}
		
		// Order by title after default ordering
		$orderby .= ($order_col_1st != $i_as.'.title' && $order_col_2nd != $i_as.'.title')  ?  ', '.$i_as.'.title'  :  '';
		$order = $order_arr;
		return $orderby;
	}
	
	
	// Create order clause sub-parts
	static function _getOrderByClause(&$params, &$order='', $i_as='i', $rel_as='rel', &$order_col='', &$order_dir='', $sfx='')
	{
		// 'order' contains a symbolic order name to indicate using the category / global ordering setting
		switch ($order) {
			case 'date': case 'addedrev': /* 2nd is for module */
				$order_col	= $i_as.'.created';
				$order_dir	= 'ASC';
				break;
			case 'rdate': case 'added': /* 2nd is for module */
				$order_col	= $i_as.'.created';
				$order_dir	= 'DESC';
				break;
			case 'modified': case 'updated': /* 2nd is for module */
				$order_col	= $i_as.'.modified';
				$order_dir	= 'DESC';
				break;
			case 'published':
				$order_col	= $i_as.'.publish_up';
				$order_dir	= 'DESC';
				break;
			case 'alpha':
				$order_col	= $i_as.'.title';
				$order_dir	= 'ASC';
				break;
			case 'ralpha': case 'alpharev': /* 2nd is for module */
				$order_col	= $i_as.'.title';
				$order_dir	= 'DESC';
				break;
			case 'author':
				$order_col	= 'u.name';
				$order_dir	= 'ASC';
				break;
			case 'rauthor':
				$order_col	= 'u.name';
				$order_dir	= 'DESC';
				break;
			case 'hits':
				$order_col	= $i_as.'.hits';
				$order_dir	= 'ASC';
				break;
			case 'rhits': case 'popular': /* 2nd is for module */
				$order_col	= $i_as.'.hits';
				$order_dir	= 'DESC';
				break;
			case 'order': case 'catorder': /* 2nd is for module */
				$order_col	= $rel_as.'.catid, '.$rel_as.'.ordering ASC, '.$i_as.'.id DESC';
				$order_dir	= '';
				break;

			// SPECIAL case custom field
			case 'field':
				$cf = $sfx == '_2nd' ? 'f2' : 'f';
				$order_col	= $params->get('orderbycustomfieldint'.$sfx, 0) ? 'CAST('.$cf.'.value AS UNSIGNED)' : $cf.'.value';
				$order_dir	= $params->get('orderbycustomfielddir'.$sfx, 'ASC');
				break;

			// NEW ADDED
			case 'random':
				$order_col	= 'RAND()';
				$order_dir	= '';
				break;
			case 'commented':
				$order_col	= 'comments_total';
				$order_dir	= 'DESC';
				break;
			case 'rated':
				$order_col	= 'votes';
				$order_dir	= 'DESC';
				break;
			case 'id':
				$order_col	= $i_as.'.id';
				$order_dir	= 'DESC';
				break;
			case 'rid':
				$order_col	= $i_as.'.id';
				$order_dir	= 'ASC';
				break;

			case 'default':
			default:
				$order_col	= $order_col ? $order_col : $i_as.'.title';
				$order_dir	= $order_dir ? $order_dir : 'ASC';
				break;
		}
	}


	/**
	 * Build the order clause of category listings
	 *
	 * @access private
	 * @return string
	 */
	static function buildCatOrderBy(&$params, $order='', $request_var='', $config_param='cat_orderby', $c_as='c', $u_as='u', $default_order_col='', $default_order_dir='')
	{
		// Use global params ordering if parameters were not given
		if (!$params) $params = JComponentHelper::getParams( 'com_flexicontent' );

		// 1. If forced ordering not given, then use ordering parameters from configuration
		if (!$order) {
			$order = $params->get($config_param, 'default');
		}

		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$order = $request_var && ($request_order = JRequest::getVar($request_var.$sfx)) ? $request_order : $order;

		switch ($order) {
			case 'date' :                  // *** J2.5 only ***
				$order_col = $c_as.'.created_time';
				$order_dir = 'ASC';
				break;
			case 'rdate' :                 // *** J2.5 only ***
				$order_col = $c_as.'.created_time';
				$order_dir = 'DESC';
				break;
			case 'modified' :              // *** J2.5 only ***
				$order_col = $c_as.'.modified_time';
				$order_dir = 'DESC';
				break;
			case 'alpha' :
				$order_col = $c_as.'.title';
				$order_dir = 'ASC';
				break;
			case 'ralpha' :
				$order_col = $c_as.'.title';
				$order_dir = 'DESC';
				break;
			case 'author' :                // *** J2.5 only ***
				$order_col = $u_as.'.name';
				$order_dir = 'ASC';
				break;
			case 'rauthor' :               // *** J2.5 only ***
				$order_col = $u_as.'.name';
				$order_dir = 'DESC';
				break;
			case 'hits' :                  // *** J2.5 only ***
				$order_col = $c_as.'.hits';
				$order_dir = 'ASC';
				break;
			case 'rhits' :                 // *** J2.5 only ***
				$order_col = $c_as.'.hits';
				$order_dir = 'DESC';
				break;
			case 'order' :
				$order_col = !FLEXI_J16GE ? $c_as.'.ordering' : $c_as.'.lft';
				$order_dir = 'ASC';
				break;
			case 'default' :
			default:
				$order_col = $default_order_col ? $default_order_col : $i_as.'.title';
				$order_dir = $default_order_dir ? $default_order_dir : 'ASC';
				break;
		}

		$orderby 	= ' ORDER BY '.$order_col.' '.$order_dir;
		$orderby .= $order_col!=$c_as.'.title' ? ', '.$c_as.'.title' : '';   // Order by title after default ordering

		return $orderby;
	}


	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	static function checkin($tbl, $redirect_url, & $controller)
	{
		$cid  = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$pk   = (int)$cid[0];
		$user = JFactory::getUser();
		$controller->setRedirect( $redirect_url, '' );

		static $canCheckinRecords = null;
		if ($canCheckinRecords === null) {
			if (FLEXI_J16GE) {
				$canCheckinRecords = $user->authorise('core.admin', 'checkin');
			} else if (FLEXI_ACCESS) {
				$canCheckinRecords = ($user->gid < 25) ? FAccess::checkComponentAccess('com_checkin', 'manage', 'users', $user->gmid) : 1;
			} else {
				// Only admin or super admin can check-in
				$canCheckinRecords = $user->gid >= 24;
			}
		}

		// Only attempt to check the row in if it exists.
		if ($pk)
		{
			// Get an instance of the row to checkin.
			$table = JTable::getInstance($tbl, '');
			if (!$table->load($pk))
			{
				$controller->setError($table->getError());
				return;// false;
			}

			// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (b) record checked out by current user
			if ($table->checked_out) {
				if ( !$canCheckinRecords && $table->checked_out != $user->id) {
					$controller->setError(JText::_( 'FLEXI_RECORD_CHECKED_OUT_DIFF_USER'));
					return;// false;
				}
			}

			// Attempt to check the row in.
			if (!$table->checkin($pk))
			{
				$controller->setError($table->getError());
				return;// false;
			}
		}

		$controller->setRedirect( $redirect_url, JText::sprintf('FLEXI_RECORD_CHECKED_IN_SUCCESSFULLY', 1) );
		return;// true;
	}
	
	
	/**
	 * Return field types grouped or not
	 *
	 * @return array
	 * @since 1.5
	 */
	static function getfieldtypes($group=false)
	{
		$db = JFactory::getDBO();

		$query = 'SELECT element AS value, REPLACE(name, "FLEXIcontent - ", "") AS text'
		. ' FROM '.(FLEXI_J16GE ? '#__extensions' : '#__plugins')
		. ' WHERE '.(FLEXI_J16GE ? 'enabled = 1' : 'published = 1')
		. (FLEXI_J16GE ? ' AND `type`=' . $db->Quote('plugin') : '')
		. ' AND folder = ' . $db->Quote('flexicontent_fields')
		. ' AND element <> ' . $db->Quote('core')
		. ' ORDER BY text ASC'
		;

		$db->setQuery($query);
		$field_types = $db->loadObjectList('value');
		
		if (!$group) return $field_types;
		
		$ft_grps = array(
			'Selection fields'         => array('radio', 'radioimage', 'checkbox', 'checkboximage', 'select', 'selectmultiple'),
			'Media fields / Mini apps' => array('file', 'image', 'minigallery', 'sharedvideo', 'sharedaudio', 'addressint'),
			'Single property fields'   => array('date', 'text', 'textarea', 'textselect'),
			'Multi property fields'     => array('weblink', 'email', 'extendedweblink', 'phonenumbers', 'termlist'),
			'Item form'                => array('groupmarker', 'coreprops'),
			'Item relations fields'    => array('relation', 'relation_reverse', 'autorelationfilters'),
			'Special action fields'    => array('toolbar', 'fcloadmodule', 'fcpagenav', 'linkslist')
		);
		foreach($ft_grps as $ft_grpname => $ft_arr) {
			foreach($ft_arr as $ft) {
				if ( !empty($field_types[$ft]) )
				$field_types_grp[$ft_grpname][$ft] = $field_types[$ft];
				unset($field_types[$ft]);
			}
		}
		// Remaining fields
		$field_types_grp['3rd-Party / Other Fields'] = $field_types;
		
		return $field_types_grp;
	}
	
	
	/**
	 * Method to get data/parameters of thie given or all types
	 *
	 * @access public
	 * @return object
	 */
	static function getTypeData($contenttypes_list)
	{
		static $cached = null;
		if ( isset($cached[$contenttypes_list]) ) return $cached[$contenttypes_list];
		
		// Retrieve item's Content Type parameters
		$db = JFactory::getDBO();
		$query = 'SELECT * '
				. ' FROM #__flexicontent_types AS t'
				. ($contenttypes_list ? ' WHERE id IN('.$contenttypes_list.')' : '')
				;
		$db->setQuery($query);
		$types = $db->loadObjectList('id');
		foreach ($types as $type) $type->params = FLEXI_J16GE ? new JRegistry($type->attribs) : new JParameter($type->attribs);
		
		$cached[$contenttypes_list] = $types;
		return $types;
	}
}


function FLEXISubmenu($cando)
{
	$perms   = FlexicontentHelperPerm::getPerm();
	$app     = JFactory::getApplication();
	$session = JFactory::getSession();
	$cparams = JComponentHelper::getParams( 'com_flexicontent' );
	
	// Check access to current management tab
	$not_authorized = isset($perms->$cando) && !$perms->$cando;
	if ( $not_authorized ) {
		$app->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
	}
	
	// Get post-installation FLAG (session variable), and current view (HTTP request variable)
	$dopostinstall = $session->get('flexicontent.postinstall');
	$view = JRequest::getVar('view', 'flexicontent');
	
	// Create Submenu, Dashboard (HOME is always added, other will appear only if post-installation tasks are done)
	$addEntry = array(FLEXI_J30GE ? 'JHtmlSidebar' : 'JSubMenuHelper', 'addEntry');
	
	if (FLEXI_J30GE) call_user_func($addEntry, '<h2 class="fcsbnav-content-editing">'.JText::_( 'FLEXI_NAV_SD_CONTENT_EDITING' ).'</h2>', '', '');
	call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-flexicontent"></span>' : '').JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent', !$view || $view=='flexicontent');
	if ($dopostinstall && version_compare(PHP_VERSION, '5.0.0', '>'))
	{
		call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-items"></span>' : '').JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items', $view=='items');
		if ($perms->CanCats) 			call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-fc_categories"></span>' : '').JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories', $view=='categories');
		if ($cparams->get('comments')==1 && $perms->CanComments) call_user_func($addEntry,
			'<a href="index.php?option=com_jcomments&task=view&fog=com_flexicontent" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;">'.
				(FLEXI_J30GE ? '<span class="fcsb-icon-comments"></span>' : '').JText::_( 'FLEXI_COMMENTS' ).
			'</a>', '', false);
		else if ($cparams->get('comments')==1 && !$perms->JComments_Installed) call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-comments disabled"></span>' : '').'<span class="fc_sidebar_entry disabled">'.JText::_( 'FLEXI_JCOMMENTS_MISSING' ).'</span>', '', false);
		
		if (FLEXI_J30GE) call_user_func($addEntry, '<h2 class="fcsbnav-type-fields">'.JText::_( 'FLEXI_NAV_SD_TYPES_N_FIELDS' ).'</h2>', '', '');
		if ($perms->CanTypes)			call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-types"></span>' : '').JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types', $view=='types');
		if ($perms->CanFields) 		call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-fields"></span>' : '').JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields', $view=='fields');
		if ($perms->CanTags) 			call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-tags"></span>' : '').JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags', $view=='tags');
		if ($perms->CanFiles) 		call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-filemanager"></span>' : '').JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager', $view=='filemanager');
		
		if (FLEXI_J30GE) call_user_func($addEntry, '<h2 class="fcsbnav-content-viewing">'.JText::_( 'FLEXI_NAV_SD_CONTENT_VIEWING' ).'</h2>', '', '');
		if ($perms->CanTemplates)	call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-templates"></span>' : '').JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates', $view=='templates');
		if ($perms->CanIndex)			call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-search"></span>' : '').JText::_( 'FLEXI_SEARCH_INDEXES' ), 'index.php?option=com_flexicontent&view=search', $view=='search');
		if ($perms->CanStats)			call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-stats"></span>' : '').JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats', $view=='stats');
		
		if (FLEXI_J30GE) call_user_func($addEntry, '<h2 class="fcsbnav-users">'.JText::_( 'FLEXI_NAV_SD_USERS_N_GROUPS' ).'</h2>', '', '');
		if ($perms->CanAuthors)		call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-users"></span>' : '').JText::_( 'FLEXI_USERS' ), 'index.php?option=com_flexicontent&view=users', $view=='users');
		if ($perms->CanGroups)		call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-groups"></span>' : '').JText::_( 'FLEXI_GROUPS' ), 'index.php?option=com_flexicontent&view=groups', $view=='groups');
	//if ($perms->CanArchives)	call_user_func($addEntry, '<span class="fcsb-icon-archive"></span>'.JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive', $view=='archive');
	
		if (FLEXI_J30GE) call_user_func($addEntry, '<h2 class="fcsbnav-expert">'.JText::_( 'FLEXI_NAV_SD_EXPERT_USAGE' ).'</h2>', '', '');
		if ($perms->CanImport)		call_user_func($addEntry, (FLEXI_J30GE ? '<span class="fcsb-icon-import"></span>' : '').JText::_( 'FLEXI_IMPORT' ), 'index.php?option=com_flexicontent&view=import', $view=='import');
		if ($perms->CanPlugins) call_user_func($addEntry,
			'<a href="index.php?option=com_plugins" onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;" >'.
				(FLEXI_J30GE ? '<span class="fcsb-icon-plugins"></span>' : '').JText::_( 'FLEXI_PLUGINS' ).
			'</a>', '', false);
	}
}


/*	
*	fcjsJText Helper for Joomla 1.5
*
*	original Author: 	Robert Gerald Porter <rob@weeverapps.com>
*	License: 	GPL v3.0
*
*/
class fcjsJText extends JText
{
	protected static $strings=array();

	/**
	 * Translate a string into the current language and stores it in the JavaScript language store.
	 *
	 * @param	string	The JText key.
	 * @since	1.6
	 * 
	 * Backport for Joomla 1.5
	 * Example use: 
	 *
	 * //use this method call for each string you will be needing in javascript
	 * fcjsJText::script("MY_FIRST_COMPONENT_STRING_NEEDED_IN_JS");
	 * fcjsJText::script("MY_NTH_COMPONENT_STRING_NEEDED_IN_JS");  
	 * // and so on�
	 * // you must then call load(), as below:
	 * fcjsJText::load();
	 *
	 * in the JS files, load localization via:
	 *
	 * //String is loaded in javascript via Joomla.JText._() method
	 * alert( Joomla.JText._('MY_FIRST_COMPONENT_STRING_NEEDED_IN_JS') );
	 * 				
	 */
	public static function script($string = null, $jsSafe = false, $interpretBackSlashes = true)
	{
		static $language = null;
		if ($language===null) $language = JFactory::getLanguage();
		
		// Add the string to the array if not null.
		if ($string !== null) {
			// Normalize the key and translate the string.
			self::$strings[strtoupper($string)] = $language->_($string, $jsSafe);
		} else {
			return self::$strings;
		}
	}
	
	
	/**
	 * Load strings translated for Javascript into JS environment. To be called after all fcjsJText::script() calls have been made.
	 */
	public static function load($after_render=true)
	{
		static $loaded = null;
		if ($loaded !== null) return;
		$loaded = true;
		if (FLEXI_J16GE) return;
		
		$js = '
			
			<script type="text/javascript">
			// <![CDATA[
			if (typeof(Joomla) === "undefined")
			{
				var Joomla = {};
				if (typeof(Joomla.JText) === "undefined") {
					Joomla.JText = {
						strings: {},
						"_": function(key, def) {
							return typeof this.strings[key.toUpperCase()] !== "undefined" ? this.strings[key.toUpperCase()] : def;
						},
						load: function(object) {
							for (var key in object) {
								this.strings[key.toUpperCase()] = object[key];
							}
							return this;
						}
					};
				}
			}
			var strings = '.json_encode(self::$strings).';
			Joomla.JText.load(strings);
			// ]]>
			</script>
		';
		
		if ($after_render) {
			// Add to header, after rendering has completed ...
			$buffer = JResponse::getBody();
			$buffer = str_replace ("</head>", "\n\n" .$js ."\n\n</head>", $buffer );
			JResponse::setBody($buffer);
		} else {
			JFactory::getDocument()->addCustomTag ($js);
		}
	}

}


class flexicontent_zip extends ZipArchive {
	/**
	 * Add a directory with files and subdirectories to the archive
	 *
	 * @param string $location Full (real) pathname
	 * @param string $name Name in Archive
	 **/
	public function addDir($pathname, $name)
	{
		$this->addEmptyDir($name);
		$this->addDirDo($pathname, $name);
	}

	/**
	 * Add files & directories to archive
	 *
	 * @param string $location Full (real) pathname
	 * @param string $name Name in Archive
	 **/
	private function addDirDo($pathname, $name)
	{
		if ($name) $name .= '/';
		$pathname .= '/';

		// Read all Files in Dir
		$dir = opendir ($pathname);
		while ($file = readdir($dir))
		{
			if ($file == '.' || $file == '..') continue;

			// Rekursiv, If dir: FlxZipArchive::addDir(), else ::File();
			$do = (filetype( $pathname . $file) == 'dir') ? 'addDir' : 'addFile';
			$this->$do($pathname . $file, $name . $file);
		}
	}
}
