<?PHP
$button="Add";			
$oui=$_POST;		# initialize some variables
$stoprun="0";		
include 'includes/header.inc';
include 'includes/links.inc';
$conn=dblogin('endpoint');		#Create a DB connection
mysql_select_db('asterisk',$conn);	#Select the database

	#The user decided not to delete the record. 
If ($_POST['Submit'] == "Reset") {
	$oui="";
	$error="";
}

		#We are preparing to delete a manufacturer here
if ($_REQUEST['Submit'] == "deleteReady") {
	$sql="Select id, oui, brand from endpointman_oui_list where id = '" . $_REQUEST['id'] . "'";
	$result=mysql_query($sql,$conn);
	$row=mysql_fetch_array($result);
	$oui=$row;
	$error="<font color='red'>You are about to permanently delete a manufacturer. <br>";
	$error.="Click delete to continue or Reset to abort the deletion.</font>";
	$button="Delete";
	

}
			# We are deleting a record here
If ($_REQUEST['Submit'] == "Delete") {
	$sql = "Delete from endpointman_oui_list where id='" . $_REQUEST['id'] . "'";
	mysql_query($sql,$conn) or Die("The Delete failed: " . mysql_error());
	$oui="";
	$error="";

}
			# We are adding an OUI here, since the Add button was pressed.
if ($_POST['Submit'] == "Add") {
	if (strlen($_POST['oui']) != "6" ) {
		$error="The OUI must be 6 Hexadecimal characters.<br>";
		$stoprun="1";
	}
	if ($_POST['brand'] == "") {
		$error.="The Brand cannot be blank.<br>";
		$stoprun="1";
	}
	
	$sql="Select * from endpointman_oui_list where model like '%" . $_POST['model'] . "' AND oui= '" . $_POST['oui'] . "'";
	$result=mysql_query($sql,$conn);
	$numrow=mysql_num_rows($result);
	if ($numrow < 1 AND $stoprun == "0") {
			# Create the query
		$sql="Insert into endpointman_oui_list set oui='" . $_POST['oui'] . "', ";
		$sql.= "brand='" . $_POST['brand'] . "', model='" . $_POST['model'] . "'";

			# Run the query
		$result=mysql_query($sql,$conn) or DIE("The Insert failed: " . mysql_error());
		$oui="";
	} else {
		$error.="There is already a record in the database with this OUI and model number.";
	}
	

}

			# Display the blank fields at the top to enter a new OUI in

displayoui($error,$oui,$button);

			# Run a query that displays all of the OUI's in the database
$sql = "Select * from endpointman_oui_list order by brand";
$result=mysql_query($sql,$conn) or Die("The Query failed: " . mysql_error());


echo "<table align='center' width='60%'>";
echo "<tr>";
while ($row = mysql_fetch_assoc($result)) {
	echo "<td align='center'>" . $row['oui'] . "</td><td align='center'>" . $row['brand'] . "</td><td align='center'><a href=?Submit=deleteReady&page=oui_list&id=" . $row['id'] . "><img src='images/delete.gif' border='0'></a></td>";
	echo "</tr><tr>\n";
	
}
echo "</table>";
include 'includes/footer.inc';
mysql_close($conn);	#Close the database connection
?>