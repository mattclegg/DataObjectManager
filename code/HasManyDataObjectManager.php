<?php

class HasManyDataObjectManager extends DataObjectManager
{
	public $joinField;
	public $addTitle;
	public $RelationType = "HasMany";	
	protected $htmlListEndName = 'CheckedList';
	protected $htmlListField = 'selected';
	public $template = 'RelationDataObjectManager';
	public $itemClass = 'HasManyDataObjectManager_Item';
	protected $relationAutoSetting = false;
  protected $markingPermission;
	/**
	 * Most of the code below was copied from HasManyComplexTableField.
	 * Painful, but necessary, until PHP supports multiple inheritance.
	 */
	
	function __construct($controller, $name, $sourceClass, $fieldList = null, $detailFormFields = null, $sourceFilter = "", $sourceSort = "", $sourceJoin = "")
	{
		parent::__construct($controller, $name, $sourceClass, $fieldList, $detailFormFields, $sourceFilter, $sourceSort, $sourceJoin);
		
		$this->Markable = true;

		if($controllerClass = $this->controllerClass()) {
			$this->joinField = $this->getParentIdName($controllerClass, $this->sourceClass);
		} else {
			user_error("Can't figure out the data class of $controller", E_USER_WARNING);
		}
				
	}

	
	/**
	 * Try to determine the DataObject that this field is built on top of
	 */
	function controllerClass() {
		if($this->controller instanceof DataObject) return $this->controller->class;
		elseif($this->controller instanceof ContentController) return $this->controller->data()->class;
	}
	
	public function setMarkingPermission($perm)
	{
	   $this->markingPermission = $perm;
	}
	
	public function hasMarkingPermission()
	{
	   if(is_bool($this->markingPermission))
	     return $this->markingPermission;
	   elseif($this->markingPermission)
	     return Permission::check($this->markingPermission);
     return true;
	}
	
	public function setParentClass($class)
	{
		parent::setParentClass($class);
		$this->joinField = $this->getParentIdName($class, $this->sourceClass);
	}
	
	function getQuery($limitClause = null) {
		if($this->customQuery) {
			$query = $this->customQuery;
			$query->select[] = "\"{$this->sourceClass}\".\"ID\" AS \"ID\"";
			$query->select[] = "\"{$this->sourceClass}\".\"ClassName\" AS \"ClassName\"";
			$query->select[] = "\"{$this->sourceClass}\".\"ClassName\" AS \"RecordClassName\"";
		}
		else {
			$query = singleton($this->sourceClass)->extendedSQL($this->sourceFilter, $this->sourceSort, $limitClause, $this->sourceJoin);
			
			// Add more selected fields if they are from joined table.

			$SNG = singleton($this->sourceClass);
			foreach($this->FieldList() as $k => $title) {
				if(! $SNG->hasField($k) && ! $SNG->hasMethod('get' . $k) && ! $SNG->has_one($k))
					$query->select[] = $k;
			}
		}
		return clone $query;
	}
	
	function sourceItems() {
		if($this->sourceItems)
			return $this->sourceItems;
		
		$limitClause = '';
		if(isset($_REQUEST[ 'ctf' ][ $this->Name() ][ 'start' ]) && is_numeric($_REQUEST[ 'ctf' ][ $this->Name() ][ 'start' ]))
			$limitClause = $_REQUEST[ 'ctf' ][ $this->Name() ][ 'start' ] . ", $this->pageSize";
		else
			$limitClause = "0, $this->pageSize";
		
		$dataQuery = $this->getQuery($limitClause);
		$records = $dataQuery->execute();
		$items = new DataObjectSet();
		$class = $this->sourceClass;
		foreach($records as $record) {
			$items->push(new $class($record));
		}
		
		// changed to avoid using $this->unpagedSourceItems because it fails on large datasets 
		$this->totalCount = $this->getQuery()->unlimitedRowCount();
		
		return $items;
	}
		
	function getControllerID() {
		return $this->controller->ID;
	}
	
	public function SortableClass()
	{
	   return $this->sourceClass();
	}
	
	function saveInto(DataObject $record) {
		$fieldName = $this->name;
		$saveDest = $record->$fieldName();
		
		if(! $saveDest)
			user_error("HasManyDataObjectManager::saveInto() Field '$fieldName' not found on $record->class.$record->ID", E_USER_ERROR);
		
		$items = array();
		
		if($list = $this->value[ $this->htmlListField ]) {
			if($list != 'undefined')
				$items = explode(',', trim($list,","));
		}
		
		$saveDest->setByIDList($items);
	}
	
	function ExtraData() {
		$items = array();
		// changed to avoid having to use $this->unpagedSourceItems because it fails on large datasets
		$items = $this->getSelectedIDs();
		$list = implode(',', $items);
		$value = ",";
		$value .= !empty($list) ? $list."," : "";
		$inputId = $this->id() . '_' . $this->htmlListEndName;
		return <<<HTML
		<input id="$inputId" name="{$this->name}[{$this->htmlListField}]" type="hidden" value="{$value}"/>
		<input id="{$inputId}_UnChecked" name="{$this->name}[{$this->htmlListField}_UnChecked]" type="hidden" value=""/>
HTML;
	}

	/**
	 * Returns the list of IDs that should be checked in the list.
	 * @see HasManyDataObjectManager::getSelectedIDs()
	 * @return array
	 */
	function getSelectedIDs() {
		$ids = array();
		$dataQuery = $this->getQuery();
		$dataQuery->where("\"{$this->joinField}\" = '{$this->controller->ID}'");
		$records = $dataQuery->execute();
		$class = $this->sourceClass;
		foreach($records as $record) {
			$item = new $class($record);
			$ids[] = $item->ID;
		}
		return $ids;
	}

}


class HasManyDataObjectManager_Item extends DataObjectManager_Item {
	
	function MarkingCheckbox() {
		$name = $this->parent->Name() . '[]';
		$joinVal = $this->item->{$this->parent->joinField};
		$parentID = $this->parent->getControllerID();
		$disabled = $this->parent->hasMarkingPermission() ? "" : "disabled='disabled'";
		
		if($this->parent->IsReadOnly || ($joinVal > 0 && $joinVal != $parentID))
			return "<input class=\"checkbox\" type=\"checkbox\" name=\"$name\" value=\"{$this->item->ID}\" disabled=\"disabled\"/>";
		else if($joinVal == $parentID)
			return "<input class=\"checkbox\" type=\"checkbox\" name=\"$name\" value=\"{$this->item->ID}\" checked=\"checked\" $disabled />";
		else
			return "<input class=\"checkbox\" type=\"checkbox\" name=\"$name\" value=\"{$this->item->ID}\" $disabled />";
	}
}



?>