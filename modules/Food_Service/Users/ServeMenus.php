<?php

StaffWidgets('fsa_barcode');
StaffWidgets('fsa_exists_Y');

Search('staff_id',$extra);

if ($_REQUEST['modfunc']=='submit')
{
	if ($_REQUEST['submit']['cancel'])
	{
		if (DeletePromptX('Sale','Cancel'))
			unset($_SESSION['FSA_sale']);
	}
	elseif ($_REQUEST['submit']['save'])
	{
		if(count($_SESSION['FSA_sale']))
		{



			$items_RET = DBGet(DBQuery('SELECT DESCRIPTION,SHORT_NAME,PRICE_STAFF FROM FOOD_SERVICE_ITEMS WHERE SCHOOL_ID=\''.UserSchool().'\''),array(),array('SHORT_NAME'));

			// get next transaction id
			$id = DBGet(DBQuery('SELECT '.db_seq_nextval('FOOD_SERVICE_STAFF_TRANSACTIONS_SEQ').' AS SEQ_ID '.FROM_DUAL));
			$id = $id[1]['SEQ_ID'];

			$item_id = 0;
			foreach($_SESSION['FSA_sale'] as $item_sn)
			{

				$price = $items_RET[$item_sn][1]['PRICE_STAFF'];












				$fields = 'ITEM_ID,TRANSACTION_ID,AMOUNT,SHORT_NAME,DESCRIPTION';
				$values = "'".$item_id++."','".$id."','-".$price."','".$items_RET[$item_sn][1]['SHORT_NAME']."','".$items_RET[$item_sn][1]['DESCRIPTION']."'";
				$sql = "INSERT INTO FOOD_SERVICE_STAFF_TRANSACTION_ITEMS (".$fields.") values (".$values.")";
				DBQuery($sql);
			}

			$sql1 = "UPDATE FOOD_SERVICE_STAFF_ACCOUNTS SET TRANSACTION_ID='".$id."',BALANCE=BALANCE+(SELECT sum(AMOUNT) FROM FOOD_SERVICE_STAFF_TRANSACTION_ITEMS WHERE TRANSACTION_ID='".$id."') WHERE STAFF_ID='".UserStaffID()."'";
			$fields = 'TRANSACTION_ID,STAFF_ID,SYEAR,SCHOOL_ID,BALANCE,TIMESTAMP,SHORT_NAME,DESCRIPTION,SELLER_ID';
			$values = "'".$id."','".UserStaffID()."','".UserSyear()."',(SELECT BALANCE FROM FOOD_SERVICE_STAFF_ACCOUNTS WHERE STAFF_ID='".UserStaffID()."'),CURRENT_TIMESTAMP,'".$menus_RET[$_REQUEST['menu_id']][1]['TITLE']."','".$menus_RET[$_REQUEST['menu_id']][1]['TITLE'].' - '.DBDate()."','".User('STAFF_ID')."'";
			$sql2 = 'INSERT INTO FOOD_SERVICE_STAFF_TRANSACTIONS ('.$fields.') values ('.$values.')';
			DBQuery('BEGIN; '.$sql1.'; '.$sql2.'; COMMIT');

			unset($_SESSION['FSA_sale']);
		}
		unset($_REQUEST['modfunc']);
	}
	else
		unset($_REQUEST['modfunc']);
	unset($_REQUEST['submit']);
}

if(UserStaffID() && !$_REQUEST['modfunc'])
{
	$staff = DBGet(DBQuery("SELECT s.STAFF_ID,s.FIRST_NAME||' '||s.LAST_NAME AS FULL_NAME,(SELECT BALANCE FROM FOOD_SERVICE_STAFF_ACCOUNTS WHERE STAFF_ID=s.STAFF_ID) AS BALANCE FROM STAFF s WHERE s.STAFF_ID='".UserStaffID()."'"));
	$staff = $staff[1];

	echo "<FORM action=Modules.php?modname=$_REQUEST[modname]&modfunc=submit&menu_id=$_REQUEST[menu_id] method=POST>";
	DrawHeader('',SubmitButton('Cancel Sale','submit[cancel]').SubmitButton('Complete Sale','submit[save]'));
	echo '</FORM>';

	echo '<TABLE width=100%><TR>';

	echo '<TD valign=top>'.NoInput($staff['FULL_NAME'],$staff['STAFF_ID']).'</TD>';
	echo '<TD valign=top>'.NoInput(red($staff['BALANCE']),'Balance').'</TD>';

	echo '</TR></TABLE>';
	echo '<HR>';

	if ($staff['BALANCE'])
	{
		echo '<TABLE border=0 width=100%>';
		echo '<TR><TD width=100% valign=top>';

		$RET = DBGet(DBQuery("SELECT fsti.DESCRIPTION,fsti.AMOUNT FROM FOOD_SERVICE_STAFF_TRANSACTIONS fst,FOOD_SERVICE_STAFF_TRANSACTION_ITEMS fsti WHERE fst.STAFF_ID='".UserStaffID()."' AND fst.SYEAR='".UserSyear()."' AND fst.SHORT_NAME='".$menus_RET[$_REQUEST['menu_id']][1]['TITLE']."' AND fst.TIMESTAMP BETWEEN CURRENT_DATE AND CURRENT_DATE+1 AND fsti.TRANSACTION_ID=fst.TRANSACTION_ID"));

		$columns = array('DESCRIPTION'=>'Item','AMOUNT'=>'Amount');
		ListOutput($RET,$columns,'Earlier '.$menus_RET[$_REQUEST['menu_id']][1]['TITLE'].' Sale','Earlier '.$menus_RET[$_REQUEST['menu_id']][1]['TITLE'].' Sales',$link,false,array('save'=>false,'search'=>false));

		// IMAGE
		//if($file = @fopen($picture=$StaffPicturesPath.'/'.UserSyear().'/'.UserStaffID().'.jpg','r') || $file = @fopen($picture=$StafftPicturesPath.'/'.(UserSyear()-1).'/'.UserStaffID().'.jpg','r'))
		//{
		//	fclose($file);
		//	echo '<TD rowspan=2 width=150 align=left><IMG SRC="'.$picture.'" width=150></TD>';
		//}

		echo '</TD></TR>';
		echo '<TR><TD width=100% valign=top>';

		$items_RET = DBGet(DBQuery("SELECT fsi.SHORT_NAME,fsi.DESCRIPTION,fsi.PRICE_STAFF,fsi.ICON FROM FOOD_SERVICE_ITEMS fsi,FOOD_SERVICE_MENU_ITEMS fsmi WHERE fsmi.MENU_ID='".$_REQUEST['menu_id']."' AND fsi.ITEM_ID=fsmi.ITEM_ID AND fsmi.CATEGORY_ID IS NOT NULL AND fsi.SCHOOL_ID='".UserSchool()."' ORDER BY fsi.SORT_ORDER"),array('ICON'=>'makeIcon'),array('SHORT_NAME'));
		$items = array();
		foreach($items_RET as $sn=>$item)
			$items += array($sn=>$item[1]['DESCRIPTION']);

		$LO_ret = array(array());
		foreach($_SESSION['FSA_sale'] as $id=>$item_sn)
		{

			$price = $items_RET[$item_sn][1]['PRICE_STAFF'];








			$LO_ret[] = array('SALE_ID'=>$id,'PRICE'=>$price,'DESCRIPTION'=>$items_RET[$item_sn][1]['DESCRIPTION'],'ICON'=>$items_RET[$item_sn][1]['ICON']);
		}
		unset($LO_ret[0]);

		$link['remove'] = array('link'=>"Modules.php?modname=$_REQUEST[modname]&modfunc=remove&menu_id=$_REQUEST[menu_id]",
					'variables'=>array('id'=>'SALE_ID'));
		$link['add']['html'] = array('DESCRIPTION'=>'<TABLE border=0 cellpadding=0 cellspacing=0><TR><TD>'.SelectInput('','item_sn','',$items).'</TD></TR></TABLE>',
					     'ICON'=>'<TABLE border=0 cellpadding=0 cellspacing=0><TR><TD><INPUT type=submit value=Add></TD></TR></TABLE>',
					     'remove'=>button('add'));
		$columns = array('DESCRIPTION'=>'Item','ICON'=>'Icon','PRICE'=>'Price');

		$tabs = array();
		foreach($menus_RET as $id=>$menu)
			$tabs[] = array('title'=>$menu[1]['TITLE'],'link'=>"Modules.php?modname=$_REQUEST[modname]&menu_id=$id");

		$extra = array('save'=>false,'search'=>false,
			'header'=>WrapTabs($tabs,"Modules.php?modname=$_REQUEST[modname]&menu_id=$_REQUEST[menu_id]"));

		echo '<BR>';
		echo "<FORM action=Modules.php?modname=$_REQUEST[modname]&modfunc=add&menu_id=$_REQUEST[menu_id] method=POST>";
		ListOutput($LO_ret,$columns,'Item','Items',$link,array(),$extra);
		echo '</FORM>';

		echo '</TD></TR></TABLE>';
	}
	else
		ErrorMessage(array('This user does not have a Food Service Account.'),'fatal');
}
?>
