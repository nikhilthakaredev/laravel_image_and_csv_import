<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;

class ImportController extends Controller
{
    public function csvImport(Request $request)
    {
        $request->validate(['csv'=>'required|mimes:csv,txt|max:10240']);
        $file = fopen($request->file('csv')->getRealPath(),'r');
        $header = fgetcsv($file);
        $buffer=[]; $summary=['total'=>0,'imported'=>0,'updated'=>0,'invalid'=>0,'duplicates'=>0];
        while(($row=fgetcsv($file))!==false){
            $summary['total']++;
            $data=array_combine($header,$row);
            if(!isset($data['sku'])){ $summary['invalid']++; continue; }
            $buffer[]=[
                'sku'=>$data['sku'],
                'name'=>$data['name']??'',
                'description'=>$data['description']??'',
                'price'=>$data['price']??0,
                'created_at'=>now(),
                'updated_at'=>now()
            ];
            if(count($buffer)>=100) $this->flushBuffer($buffer,$summary);
        }
        if(count($buffer)) $this->flushBuffer($buffer,$summary);
        return response()->json($summary);
    }

    protected function flushBuffer(array $buffer,array &$summary)
    {
        DB::transaction(function() use($buffer,&$summary){
            $skus=array_column($buffer,'sku');
            $existing=Product::whereIn('sku',$skus)->pluck('sku')->all();
            $toInsert=[];$toUpdate=[];
            foreach($buffer as $row){
                if(in_array($row['sku'],$existing)) $toUpdate[]=$row;
                else $toInsert[]=$row;
            }
            if(count($toInsert)) Product::insert($toInsert);
            foreach($toUpdate as $u){ Product::where('sku',$u['sku'])->update([
                'name'=>$u['name'],'description'=>$u['description'],'price'=>$u['price'],'updated_at'=>now()
            ]);}
            $summary['imported']+=count($toInsert);
            $summary['updated']+=count($toUpdate);
        });
    }
}

