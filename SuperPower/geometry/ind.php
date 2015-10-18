<?php
include_once("Geometry.class.php"); // Abtract class
include_once("Point.class.php");
include_once("Collection.class.php"); // Abtract class
include_once("LineString.class.php");
include_once("Polygon.class.php");
include_once("MultiLineString.class.php");
include_once("GeometryCollection.class.php");

$apoint = new Point(6,0);
$bpoint = new Point(0,6);
$cpoint = new Point(6,6);
$dpoint = new Point(0,0);
$aline = new LineString(array($apoint,$bpoint));
$bline = new LineString(array($cpoint,$bpoint));
$cline = new LineString(array($apoint,$cpoint));
$poly = new Polygon(array($aline,$bline,$cline));
echo $poly->pointInPolygon($dpoint);
