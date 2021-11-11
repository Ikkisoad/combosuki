<?php

function template(){
	?>
	<script>
		function resetVideo(video_id) {

		}
	</script>
	<?php
}

function setImageJS(){
	?>
	<script>
		function setImage(select,id){
		  var image = document.getElementsByName("image-"+id)[0];
		  image.src = "../../img/buttons/"+select.options[select.selectedIndex].value+".png";
		}
	</script>
	<?php
}

function changeMethodJS(){
	?>
	<script>
		function changeMethod(objButton) {
				if(document.getElementById("MyForm").method == "post"){
					$("#MyForm").attr("method", "get");
					objButton.value = "Search";
				}else{
					$("#MyForm").attr("method", "post");
					objButton.value = "Submit";
				}
			}
	</script>
	<?php
}

function backspaceJS(){
	?>
	<script>
		function backspace(){
			var txt=document.getElementById("comboarea").value;
			if(txt.length == 0){return;}
			if(txt[txt.length-1] == " ")txt = txt.substring(0, txt.length - 1);
			while(txt[txt.length-1] != " " ){
				if(txt.length == 1){
					txt = "";
					break;
				}
				txt = txt.substring(0, txt.length - 1);
				if(txt.legth == 0){
					break;
				}
			}
			document.getElementById("comboarea").value=txt; 
		}
	</script>
	<?php
}

function moveNumbersJS(){
	?>
	<script>
		function moveNumbers(num) { 
				var txt=document.getElementById("comboarea").value; 
				txt=txt + num + " "; 
				document.getElementById("comboarea").value=txt; 
		}
	</script>
	<?php
}

function changeDisplayJS(){
	?>
	<script>
		function change_display(){
			var temp = document.getElementById("combo_line").innerHTML;
			document.getElementById("combo_line").innerHTML = document.getElementById("combo_text").innerHTML;
			document.getElementById("combo_text").innerHTML = temp;
		}
	</script>
	<?php
}

function returnColorJS(){
	?>
	<script>
		function returnColor(color) {
			document.getElementById("headcolor").value = color;
		}
	</script>
	<?php
}

function copytoclipJS(){
	?>
	<script>
		function copytoclip(link) {
			var dummy = document.createElement("textarea");
			document.body.appendChild(dummy);
			dummy.value = link;
			dummy.select();
			document.execCommand("copy");
			document.body.removeChild(dummy);
		}
	</script>
	<?php
}

function playVideoJS(){
	?>
	<script>
		function playVideo(video_id) {
			var video = document.getElementById(video_id);
			var src = video.dataset.src;
			video.src = src;
		}
	</script>
	<?php
}

function resetVideoJS(){
	?>
	<script>
		function resetVideo(video_id) {
			var video = document.getElementById(video_id);
			var src = video.src.replace('?autoplay=1', '');

			video.src = '';
			video.dataset.src = src;
		}
	</script>
	<?php
}

function showDIV_JS(){
	?>
	<script>
		function showDIV(DIV_ID) {
			var x = document.getElementById(DIV_ID);
			if (x.style.display === "none") {
				x.style.display = "block";
				playVideo('v'+DIV_ID);
			}else{
				x.style.display = "none";
				resetVideo('v'+DIV_ID);
			}
		}
	</script>
	<?php
}


function sortTableJS(){
	?>
	<script>
		function sortTable(n,isNumber) {
			  var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
			  table = document.getElementById("myTable");
			  switching = true;
			  //Set the sorting direction to ascending:
			  dir = "asc"; 
			  /*Make a loop that will continue until
			  no switching has been done:*/
			  while (switching) {
				//start by saying: no switching is done:
				switching = false;
				rows = table.rows;
				/*Loop through all table rows (except the
				first, which contains table headers):*/
				for (i = 1; i < (rows.length - 1); i++) {
				  //start by saying there should be no switching:
				  shouldSwitch = false;
				  /*Get the two elements you want to compare,
				  one from current row and one from the next:*/
				  x = rows[i].getElementsByTagName("TD")[n];
				  y = rows[i + 1].getElementsByTagName("TD")[n];
				  /*check if the two rows should switch place,
				  based on the direction, asc or desc:*/
				  if (dir == "asc") {
					if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase() && !isNumber) {
					  //if so, mark as a switch and break the loop:
					  shouldSwitch= true;
					  break;
					}else if(Number(x.innerHTML.toLowerCase()) > Number(y.innerHTML.toLowerCase())){
						shouldSwitch= true;
						break;
					}
				  } else if (dir == "desc") {
					if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase() && !isNumber) {
					  //if so, mark as a switch and break the loop:
					  shouldSwitch = true;
					  break;
					}else if(Number(x.innerHTML.toLowerCase()) < Number(y.innerHTML.toLowerCase())){
						shouldSwitch= true;
						break;
					}
				  }
				}
				if (shouldSwitch) {
				  /*If a switch has been marked, make the switch
				  and mark that a switch has been done:*/
				  rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
				  switching = true;
				  //Each time a switch is done, increase this count by 1:
				  switchcount ++;      
				} else {
				  /*If no switching has been done AND the direction is "asc",
				  set the direction to "desc" and run the while loop again.*/
				  if (switchcount == 0 && dir == "asc") {
					dir = "desc";
					switching = true;
				  }
				}
			  }
			}
	</script>
	<?php
}


?>