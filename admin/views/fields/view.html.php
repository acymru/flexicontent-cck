<?php
/**
 * @version 1.5 stable $Id: view.html.php 1901 2014-05-07 02:37:25Z ggppdk $
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

jimport('joomla.application.component.view');

/**
 * View class for the FLEXIcontent categories screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFields extends JViewLegacy 
{
	function display( $tpl = null )
	{
		//initialise variables
		$app      = JFactory::getApplication();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$option   = JRequest::getCmd('option');
		$view     = JRequest::getVar('view');
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		flexicontent_html::loadFramework('select2');
		JHTML::_('behavior.tooltip');

		// Get filters
		$count_filters = 0;
		
		//get vars
		$filter_fieldtype = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_fieldtype', 	'filter_fieldtype', 	'', 'word' );
		$filter_assigned  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_assigned', 	'filter_assigned', 	'', 'word' );
		$filter_type      = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_type', 		'filter_type', 		'', 'int' );
		$filter_state 		= $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state', 		'filter_state', 	'', 'word' );
		$filter_access    = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access',    'filter_access',    '', 'string' );
		if ($filter_assigned) $count_filters++; if ($filter_fieldtype) $count_filters++;
		if ($filter_state) $count_filters++; if ($filter_access) $count_filters++;
		if ($filter_type) $count_filters++;
		
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order', 		'filter_order', 	't.ordering', 'cmd' );
		if ($filter_type && $filter_order == 't.ordering') {
			$filter_order	= $app->setUserState( $option.'.'.$view.'.filter_order', 'typeordering' );
		} else if (!$filter_type && $filter_order == 'typeordering') {
			$filter_order	= $app->setUserState( $option.'.'.$view.'.filter_order', 't.ordering' );
		}
		$filter_order_Dir	= $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir',	'filter_order_Dir',	'ASC', 'word' );
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search = FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );
		if (strlen($search)) $count_filters++;
		
		if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
		{
			$notice_content_type_order = $app->getUserStateFromRequest( $option.'.'.$view.'.notice_content_type_order',	'notice_content_type_order',	0, 'int' );
			if (!$notice_content_type_order) {
				$app->setUserState( $option.'.'.$view.'.notice_content_type_order', 1 );
				$app->enqueueMessage(JText::_('FLEXI_DEFINE_FIELD_ORDER_FILTER_BY_TYPE'), 'notice');
				$app->enqueueMessage(JText::_('FLEXI_DEFINE_FIELD_ORDER_FILTER_WITHOUT_TYPE'), 'notice');
				$app->enqueueMessage(JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF'), 'message');
			}
		}
		
		// Add custom css and js to document
		$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j15.css');
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanFields');
		
		
		// ******************
		// Create the toolbar
		// ******************
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_FIELDS' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'fields' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		$contrl = FLEXI_J16GE ? "fields." : "";
		if ($perms->CanCopyFields) {
			JToolBarHelper::custom( $contrl.'copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY' );
			JToolBarHelper::custom( $contrl.'copy_wvalues', 'copy_wvalues.png', 'copy_f2.png', 'FLEXI_COPY_WITH_VALUES' );
			JToolBarHelper::divider();
		}
		JToolBarHelper::publishList($contrl.'publish');
		JToolBarHelper::unpublishList($contrl.'unpublish');
		if ($perms->CanAddField) {
			JToolBarHelper::addNew($contrl.'add');
		}
		if ($perms->CanEditField) {
			JToolBarHelper::editList($contrl.'edit');
		}
		if ($perms->CanDeleteField) {
			//JToolBarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			// This will work in J2.5+ too and is offers more options (above a little bogus in J1.5, e.g. bad HTML id tag)
			$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE') );
			$msg_confirm = JText::_('FLEXI_ITEMS_DELETE_CONFIRM');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			flexicontent_html::addToolBarButton(
				'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		}
		
		JToolBarHelper::divider(); JToolBarHelper::spacer();
		$toggle_icon = 'basicindex';
		$btn_task    = FLEXI_J16GE ? 'fields.toggleprop' : 'toggleprop';
		$extra_js    = "document.getElementById('adminForm').elements['propname'].value='issearch';";
		flexicontent_html::addToolBarButton(
			'FLEXI_TOGGLE_TEXT_SEARCHABLE', $toggle_icon, $full_js='', $msg_alert=JText::_('FLEXI_SELECT_FIELDS_TO_TOGGLE_PROPERTY'), $msg_confirm='',
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=false, $btn_class="btn-info");
		
		$toggle_icon = 'basicfilter';
		$btn_task    = FLEXI_J16GE ? 'fields.toggleprop' : 'toggleprop';
		$extra_js    = "document.getElementById('adminForm').elements['propname'].value='isfilter';";
		flexicontent_html::addToolBarButton(
			'FLEXI_TOGGLE_FILTERABLE', $toggle_icon, $full_js='', $msg_alert=JText::_('FLEXI_SELECT_FIELDS_TO_TOGGLE_PROPERTY'), $msg_confirm='',
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=false, $btn_class="btn-info");
		
		$toggle_icon = 'advindex';
		$btn_task    = FLEXI_J16GE ? 'fields.toggleprop' : 'toggleprop';
		$extra_js    = "document.getElementById('adminForm').elements['propname'].value='isadvsearch';";
		flexicontent_html::addToolBarButton(
			'FLEXI_TOGGLE_ADV_TEXT_SEARCHABLE', $toggle_icon, $full_js='', $msg_alert=JText::_('FLEXI_SELECT_FIELDS_TO_TOGGLE_PROPERTY'), $msg_confirm='',
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=false, $btn_class="btn-info");
		
		$toggle_icon = 'advfilter';
		$btn_task    = FLEXI_J16GE ? 'fields.toggleprop' : 'toggleprop';
		$extra_js    = "document.getElementById('adminForm').elements['propname'].value='isadvfilter';";
		flexicontent_html::addToolBarButton(
			'FLEXI_TOGGLE_ADV_FILTERABLE', $toggle_icon, $full_js='', $msg_alert=JText::_('FLEXI_SELECT_FIELDS_TO_TOGGLE_PROPERTY'), $msg_confirm='',
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=false, $btn_class="btn-info");
		
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}
		
		// Get data from the model
		$model = $this->getModel();
		$rows       = $this->get( FLEXI_J16GE ? 'Items' : 'Data' );
		$pagination = $this->get( 'Pagination' );
		$types      = $this->get( 'Typeslist' );
		$fieldtypes = $model->getFieldtypes($fields_in_groups = true);

		$lists = array();
		
		
		// build item-type filter
		$lists['filter_type'] = ($filter_type|| 1 ? '<label class="label">'.JText::_('FLEXI_TYPE').'</label>' : '').
			flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, '-'/*2*/, 'class="use_select2_lib" size="1" onchange="submitform( );"', 'filter_type');
		
		
		// build orphaned/assigned filter
		$assigned 	= array();
		$assigned[] = JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FIELDS' )*/ );
		$assigned[] = JHTML::_('select.option',  'O', JText::_( 'FLEXI_ORPHANED' ) );
		$assigned[] = JHTML::_('select.option',  'A', JText::_( 'FLEXI_ASSIGNED' ) );

		$lists['assigned'] = ($filter_assigned || 1 ? '<label class="label">'.JText::_('FLEXI_ASSIGNED').'</label>' : '').
			JHTML::_('select.genericlist', $assigned, 'filter_assigned', 'class="use_select2_lib" size="1" onchange="submitform( );"', 'value', 'text', $filter_assigned );
		
		
		// build field-type filter
		$ALL = mb_strtoupper(JText::_( 'FLEXI_ALL' ), 'UTF-8') . ' : ';
		$fftype 	= array();
		$fftype[] = JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_ALL_FIELDS_TYPE' )*/ );
		$fftype[] = JHTML::_('select.option',  'BV', $ALL . JText::_( 'FLEXI_BACKEND_FIELDS' ) );
		$fftype[] = JHTML::_('select.option',  'C', $ALL . JText::_( 'FLEXI_CORE_FIELDS' ) );
		$fftype[] = JHTML::_('select.option',  'NC', $ALL . JText::_( 'FLEXI_NON_CORE_FIELDS' ) );
		
		foreach ($fieldtypes as $field_group => $ft_types) {
			$fftype[] = JHTML::_('select.optgroup', $field_group );
			foreach ($ft_types as $field_type => $ftdata) {
				$field_friendlyname = str_ireplace("FLEXIcontent - ","",$ftdata->field_friendlyname);
				$fftype[] = JHTML::_('select.option', $field_type, '-'.$ftdata->assigned.'- '. $field_friendlyname);
			}
			$fftype[] = JHTML::_('select.optgroup', '' );
		}
		
		$lists['fftype'] = ($filter_fieldtype || 1 ? '<label class="label">'.JText::_('FLEXI_FIELD_TYPE').'</label>' : '').
			JHTML::_('select.genericlist', $fftype, 'filter_fieldtype', 'class="use_select2_lib" size="1" onchange="submitform( );"', 'value', 'text', $filter_fieldtype );
		if (!FLEXI_J16GE) $lists['fftype'] = str_replace('<optgroup label="">', '</optgroup>', $lists['fftype']);
		
		
		// build publication state filter
		$states 	= array();
		$states[] = JHTML::_('select.option',  '', '-'/*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$states[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$states[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		//$states[] = JHTML::_('select.option',  '-2', JText::_( 'FLEXI_TRASHED' ) );
		
		$lists['state'] = ($filter_state || 1 ? '<label class="label">'.JText::_('FLEXI_STATE').'</label>' : '').
			JHTML::_('select.genericlist', $states, 'filter_state', 'class="use_select2_lib" size="1" onchange="submitform( );"', 'value', 'text', $filter_state );
			//JHTML::_('grid.state', $filter_state );
		
		
		// build access level filter
		$options = JHtml::_('access.assetgroups');
		array_unshift($options, JHtml::_('select.option', '', '-'/*JText::_('JOPTION_SELECT_ACCESS')*/) );
		$fieldname =  $elementid = 'filter_access';
		$attribs = 'class="use_select2_lib" onchange="Joomla.submitform()"';
		$lists['access'] = ($filter_access || 1 ? '<label class="label">'.JText::_('FLEXI_ACCESS').'</label>' : '').
			JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid, $translate=true );
		
		
		// text search filter
		$lists['search']= $search;
		
		
		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		if ($filter_type == '' || $filter_type == 0)
		{
			$ordering = ($lists['order'] == 't.ordering');
		} else {
			$ordering = ($lists['order'] == 'typeordering');
		}
		
		
		//assign data to template
		$this->assignRef('count_filters', $count_filters);
		$this->assignRef('permission'		, $perms);
		$this->assignRef('filter_type'  , $filter_type);
		$this->assignRef('lists'	, $lists);
		$this->assignRef('rows'		, $rows);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('pagination'	, $pagination);
		
		$this->assignRef('option', $option);
		$this->assignRef('view', $view);
		
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
}
?>