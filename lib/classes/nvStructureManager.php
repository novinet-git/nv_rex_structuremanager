<?php

class nvStructureManager {

	public function __construct() {

	}

	public function getTree($iParentId=0,$iLevel=0) {
		$aItems = array();
		$oItems = rex_sql::factory();
		$sQuery = "SELECT catname,id,parent_id,priority FROM ".rex::getTablePrefix() . "article WHERE parent_id = '$iParentId' && startarticle = '1' ORDER BY catpriority ASC";
		$oItems->setQuery($sQuery);

		foreach($oItems AS $oItem) {
			array_push($aItems,array(name => $oItem->getValue(catname),level => $iLevel, priority => $oItem->getValue(catpriority), id => $oItem->getValue(id), parent_id => $oItem->getValue(parent_id), children => $this->getTree($oItem->getValue(id),$iLevel+1)));
		}

		return $aItems;
	}

	public function getLangauges() {
		$aItems = array();
		$oItems = rex_sql::factory();
		$sQuery = "SELECT id,code,name FROM ".rex::getTablePrefix() . "clang ORDER BY id ASC";
		$oItems->setQuery($sQuery);

		foreach($oItems AS $oItem) {
			array_push($aItems,array(name => $oItem->getValue(name),level => 0, code => $oItem->getValue(code), id => $oItem->getValue(id)));
		}

		return $aItems;
	}

	public function parseTreeList($aItems,$bIsActionCopy=true) {
		$aOut = array();

		$aOut[] = '<div class="row">';
		$aOut[] = '<div class="col-sm-4 mr-3" style="max-width:600px"><strong>Quelle</strong><br><select data-yform-tools-select2="" class="form-control select2-hidden-accessible" name="nv_source_id">'.$this->parseTreeSelection("nv_source_id",$aItems).'</select></div>';
		if ($bIsActionCopy) {
			$aOut[] = '<div class="col-sm-4"><strong>Ziel</strong><br><select data-yform-tools-select2="" class="form-control select2-hidden-accessible" name="nv_target_id"><option value="0">Kein Elternelement</option>'.$this->parseTreeSelection("nv_target_id",$aItems).'</select></div>';
			$aOut[] = '<div class="col-sm-4"><strong>Sprache</strong><br><select data-yform-tools-select2="" class="form-control select2-hidden-accessible" name="nv_clang_id">'.$this->parseTreeSelection("nv_clang_id",$this->getLangauges()).'</select></div>';
		}
		$aOut[] = '</div><br>';

		$sOut = implode("\n",$aOut);
		return $sOut;
	}

	public function parseTreeSelection($sFieldname,$aItems) {
		//print_r($aItems);
		$aOut = array();
		$sCheckValue = rex_request($sFieldname, 'int');
		foreach($aItems AS $aItem) {
			$aOut[] = '<option value="'.$aItem[id].'" ';
			if ($sCheckValue == $aItem[id]) $aOut[] = 'selected';
			$aOut[] = '>';
			for($x=0;$x<$aItem[level];$x++) {
				$aOut[] = '&nbsp;&nbsp;';
			}

			$aOut[] = $aItem[name].'</option>';
			if (count($aItem[children])) {
				$aOut[] = $this->parseTreeSelection($sFieldname,$aItem[children]);
			}
		}
		$sOut = implode("\n",$aOut);
		return $sOut;
	}

	public function deleteCategory($iId) {
		$oCategory = rex_sql::factory()->setQuery("SELECT * FROM ".rex::getTablePrefix()."article WHERE id = '$iId' Limit 1");
		$iClangId = $oCategory->getValue(clang_id);
		$iParentId = $oCategory->getValue(parent_id);
		$aArticles = $this->getArticles($iId,$iClangId);
		foreach($aArticles AS $iArticleId) {
			rex_article_service::_deleteArticle($iArticleId);
		}

		$aChildrenCategories = $this->getChildrenCategories($iId,$iClangId);
		foreach($aChildrenCategories AS $iCategoryId) {
			$this->deleteCategory($iCategoryId);
		}

		rex_article_service::_deleteArticle($iId);
		rex_category_service::newCatPrio($iParentId,$iClangId,0,1);
	}

	public function copyCategory($iSourceId,$iTargetId,$iClangId,$bIsRoot=false) {

		
		$iNewCategoryId = $this->copyArticle($iSourceId,$iTargetId,$iClangId,$bIsRoot);



		// get articles
		$aArticles = $this->getArticles($iSourceId,$iClangId);

		foreach($aArticles AS $iArticleId) {
			$iNewArticleId = rex_article_service::copyArticle($iArticleId,$iNewCategoryId);

			$oArticle = rex_sql::factory()->setQuery("SELECT * FROM ".rex::getTablePrefix()."article WHERE id = '$iNewArticleId' Limit 1");
			$sName = $oArticle->getValue(name);
			$sName = str_replace(" ".rex_i18n::msg('structure_copy'),"",$sName);
			$oDb = rex_sql::factory();
			$oDb->setTable(rex::getTablePrefix() . 'article');
			$oDb->setWhere(['id' => $iNewArticleId]);
			$oDb->setValue('name', $sName);
			$oDb->update();
		}

		// get children categories
		$aChildrenCategories = $this->getChildrenCategories($iSourceId,$iClangId);
		foreach($aChildrenCategories AS $iCategoryId) {
			$this->copyCategory($iCategoryId,$iNewCategoryId,$iClangId);
		}
	}

	function getChildrenCategories($iParentId,$iClangId) {
		$aCategories = array();
		$oSql = rex_sql::factory();
		$oSql->setQuery("SELECT * FROM ".rex::getTablePrefix()."article WHERE parent_id = '$iParentId' && clang_id = '$iClangId' && catpriority != '0' ORDER BY catpriority ASC");
		foreach ($oSql AS $oCategories) {
			array_push($aCategories, $oCategories->getValue(id));
		}
		return $aCategories;
	}

	function getArticles($iParentId,$iClangId) {
		$aArticles = array();
		$oSql = rex_sql::factory();
		$oSql->setQuery("SELECT * FROM ".rex::getTablePrefix()."article WHERE parent_id = '$iParentId' && clang_id = '$iClangId' && catpriority = '0' ORDER BY priority ASC");
		foreach ($oSql AS $oArticles) {
			array_push($aArticles, $oArticles->getValue(id));
		}
		return $aArticles;
	}

	function copyArticle($iSourceId,$iTargetId,$iClangId,$bIsRoot=false) {
		$oSource = rex_sql::factory()->setQuery("SELECT * FROM ".rex::getTablePrefix()."article WHERE id = '$iSourceId' && clang_id = '$iClangId' Limit 1");
		$oTarget = rex_sql::factory()->setQuery("SELECT * FROM ".rex::getTablePrefix()."article WHERE id = '$iTargetId' && clang_id = '$iClangId' Limit 1");

		$oTmp = rex_sql::factory()->setQuery("SELECT * FROM ".rex::getTablePrefix()."article WHERE parent_id = '$iTargetId' && startarticle = '1' && clang_id = '$iClangId' ORDER BY catpriority DESC Limit 1");
		$iCatPriority = $oTmp->getValue(catpriority)+1;


		$aArticleColumns = rex_sql_table::get(rex::getTablePrefix()."article")->getColumns();
		$aIgnoreArticleColumns = array(pid,id,name,catname,parent_id,catpriority,path,createdate,updatedate,createuser,updateuser,status);
		foreach($aIgnoreArticleColumns AS $sCol) {
			unset($aArticleColumns[$sCol]);
		}

		

		if ($iTargetId) {
			$sPath = $oTarget->getValue(path).$iTargetId."|";
		} else {
			$sPath = "|";
		}


		$oArticle = rex_sql::factory();
		$oArticle->setTable(rex::getTablePrefix() . 'article');
		$iId = $oArticle->setNewId('id');

		$sName = $oSource->getValue(name);
		$sCatName = $oSource->getValue(catname);
		if ($bIsRoot) {
			$sName .= " [Kopie ".$iId."]";
			$sCatName .= " [Kopie ".$iId."]";
		}

		$oArticle->setValue('parent_id', $iTargetId);
		$oArticle->setValue('name', $sName);		
		$oArticle->setValue('catname', $sCatName);
		$oArticle->setValue('parent_id', $iTargetId);
		$oArticle->setValue('status', 0);
		$oArticle->setValue('catpriority', $iCatPriority);
		$oArticle->setValue('path', $sPath);
		foreach($aArticleColumns AS $sColumn => $sObj) {
			$oArticle->setValue($sColumn, $oSource->getValue($sColumn));
		}
		$oArticle->addGlobalCreateFields();
		$oArticle->addGlobalUpdateFields();
		$oArticle->insert();


		
		$this->copySlices($oSource->getValue(id),$iId,$iClangId);

		return $iId;
	}


	function copySlices($iSourceId,$iTargetId,$iClangId) {

		$aSliceColumns = rex_sql_table::get(rex::getTablePrefix()."article_slice")->getColumns();
		$aIgnoreSliceColumns = array(id,article_id,createdate,updatedate,createuser,updateuser);
		foreach($aIgnoreSliceColumns AS $sCol) {
			unset($aSliceColumns[$sCol]);
		}

		$oSql = rex_sql::factory();
		$oSql->setQuery("SELECT * FROM ".rex::getTablePrefix()."article_slice WHERE article_id = '$iSourceId' && clang_id = '$iClangId' ORDER BY priority ASC");
		foreach ($oSql AS $oOldSlice) {
			$oSlice = rex_sql::factory();
			$oSlice->setTable(rex::getTablePrefix() . 'article_slice');
			$oSlice->setValue('article_id', $iTargetId);
			foreach($aSliceColumns AS $sColumn => $sObj) {
				$oSlice->setValue($sColumn, $oOldSlice->getValue($sColumn));
			}
			$oSlice->addGlobalCreateFields();
			$oSlice->addGlobalUpdateFields();
			$oSlice->insert();
		}
	}

}