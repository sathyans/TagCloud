<?php

class wordCloud {
    private $font;
    private $colors;
    private $colorMode;
    private $xCanvas;
    private $yCanvas;
    private $xVal;
    private $yVal;
    private $rotationVal;
    private $constraintField;
    private $constraintValue;
    private $constraintOperator;
    private $maxItems;
    private $minCount;
    private $connection;
    private $data;
    private $dbhost;
    private $dbname;
    private $dbuser;
    private $dbpass;
    
    public function __construct($source = false, $item = false) {
        $this->source = $source;
        $this->item = $item;
        
        //Default values
        $this->dbhost="";
        $this->dbname="";
        $this->dbuser="";
        $this->dbpass="";
        
        //controls the query
        $this->constraintField = NULL;
        $this->constraintValue = NULL;
        $this->constraintOperator = TRUE;
        $this->maxItems = 0;
        $this->minCount = 0;
        
        //controls the output shape
        $this->colors = array("red","orange","green","blue","purple","grey","yellow");
        $this->colorMode = 1 ;
        $this->font = 'Helvetica';
        $this->xCanvas = 1800;
        $this->yCanvas = 800;
        $this->xVal = 0;
        $this->yVal = 15;
        $this->rotationVal = null;

    }
    
    private function getData(){
          //Fetch from db
        $query = "select ".$this->item." as 'Item', count(*) as 'Counter' from ".$this->source." WHERE 1 ";
        
        if ($this->constraintField && $this->constraintValue){
            
            $dataTypeSql = "SELECT `DATA_TYPE` FROM  `information_schema`.`COLUMNS` WHERE  `TABLE_NAME` = '".$this->source."' AND COLUMN_NAME = '".$this->constraintField."'";
            $dataTypeResults = $this->connection->query($dataTypeSql);
            $dataType = $dataTypeResults->fetchAll();
            //echo $dataType[0]['DATA_TYPE']; // returns varchar, int
            //what is the datatype of $this->constraintField
            
            $query .= " AND ".$this->constraintField;
            if (is_array($this->constraintValue)){
              if ($this->constraintOperator == FALSE){
                $query .= " NOT IN ";
              }  
              else {
                $query .= " IN ";
              }
                $valuesList = implode(",",  $this->constraintValue);
                $query .= "(".$valuesList.") ";
            }
            else {
               if ($this->constraintOperator == FALSE){
                $query .= " <> ";
              }  
              else {
                $query .= " = ";
              }
              $query .= $this->constraintValue;
            }
        }
                      
        $query .= " GROUP BY ".  $this->item;

        if ($this->minCount > 0){
            $query .= " HAVING  count(*) > ".$this->minCount;
        }
        $query .= " ORDER BY 2 DESC";
        if ($this->maxItems > 0){
            $query .= " LIMIT ".$this->maxItems;
        }
        $query .= " ;";
        //echo $query;
        $result = $this->connection->prepare($query);
        //bindValue detect number or string
        $result->execute();
        foreach($result as $row){
            $this->data[$row['Item']] = $row['Counter'];
        }      
    }

    //Specify a list of colors to use
    public function setColors($colorSet){
        $this->colors = $colorSet;
    }
    
    /* Determine how colors are ordered
     * 0 - random
     * 1 - forward sequence from begiining
     * 2 - reverse sequence from end
     * 3 - forward sequence from random starting point
     * 4 - reverse sequence from random starting point
     */
    public function setColorMode($mode){
        $this->colorMode = $mode;
    }
    
    public function setFont($font){
        $this->font = $font;
    }
    
    public function setCanvas($x,$y){
        $this->xCanvas = $x;
        $this->yCanvas = $y;
    }
    
    public function setPosition($x,$y){
        $this->xVal = $x;
        $this->yVal = $y;
    }
    
    //example setConstraint("Tag","Christmas",TRUE)
    public function setConstraint($field,$value,$operator=true){
        $this->constraintField = $field;
        $this->constraintValue = $value;
        $this->constraintOperator = $operator;
        
    }
    
    //next two functions limit number of rows returned with LIMIT and HAVING. Each takes an int.
    public function setMaxItems($maxItems){
        $this->maxItems = $maxItems;
    }
    public function setMinimumCount($minCount){
        $this->minCount = $minCount;
    }
    //Unspecified is random rotation
    public function setRotation($rotation){
        $this->rotationVal = $rotation;
    }
    
    public function setConnection($dbhost,$dbname,$dbuser,$dbpass){
        $this->dbhost = $dbhost;
        $this->dbname = $dbname;
        $this->dbuser = $dbuser;
        $this->dbpass = $dbpass;
        try {
            $this->connection=new PDO("mysql:host=$this->dbhost;dbname=$this->dbname",$this->dbuser,$this->dbpass);
        }
        catch (PDOException $e)
        {
            echo $e->getMessage();
        }

        
    }
    
    public function getConnection(){
        return $this->connection;
    }
    
    public function setConnFile($filename,$conn){
        require_once $filename;
        $this->connection = $conn;
    }
    
    //this main drawing function
    public function writeSvg(){
        $this->getData();
        switch($this->colorMode){
            case 0:
                //random
                $colorIndex = rand(0,(count($this->colors) - 1));
                break;
            case 1:
            default:
                //forward sequence
                $colorIndex=0;
                break;
            case 2:
                //reverse sequence
                $colorIndex = (count($this->colors)-1);
                break;
            case 3:
                //random start point forward
                $colorIndex = rand(0,(count($this->colors) - 1));
                break;
            case 4:
                //random start point reverse
                $colorIndex = rand(0,(count($this->colors) - 1));
                break;
        }
        echo '<h3>Word Cloud</h3><p>Sizes of words indicate the frequency of mentions.</p>';
        echo '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="'.$this->xCanvas.'px" height="'.$this->yCanvas.'px" >';
        print_r($this->data); //printed to HTML source but not rendered
        foreach ($this->data as $key=>$val) {
            if ($colorIndex >= count($this->colors)){
                $colorIndex = 0;
            }
            if ($colorIndex < 0){
                $colorIndex = (count($this->colors) - 1);
            }
            $fontSize = ($val > 500) ?  500 : $val;
            $pushDown = 150;
            $xMin = 50;
            $xMax = ($val > 500) ?  500 : ($this->xCanvas - $val)/2;
            $yMin = $pushDown;
            $xPos = rand($xMin,$xMax);
            $yPos = rand($yMin,  $this->yCanvas);
            if ($this->rotationVal){
                $rotation = $this->rotationVal;
            }
            else {
                $rotation = rand (-20,20);
            }
            $font=$this->font;
            $colors=$this->colors;
            
            echo '<a xlink:href="browseTags.php?tag='.$key.'#resultst"><text x="'.$xPos.'" y="'.$yPos.'" style=font-family:"'.$font.'" font-size="'.$fontSize.'" fill="'.$colors[$colorIndex].'" transform="rotate('.$rotation.' '.$xPos.' '.$yPos.' )">'.$key.'</text></a>
                ';
            if ($this->colorMode == 1 || $this->colorMode == 3){
                $colorIndex++;
            }
            else if ($this->colorMode == 2 || $this->colorMode == 4){
                $colorIndex--;
            }
        }
 
        echo '</svg>';
        
    }
}
?>