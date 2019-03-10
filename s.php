<?php
function convert($size)
{
	    $unit=array('b','kb','mb','gb','tb','pb');
		    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
}

//echo convert(memory_get_usage(true)); // 123 kb
echo convert(607968 ); // 123 kb
