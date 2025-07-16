<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PetrolStation extends FormRequest
{
   
    public function authorize()
    {
        return true;
    }

    
    public function rules()
    {
        return [
            "name" => "required|string|unique:petrol_station,name", 
            "type" => "required|in:IOT,NIOT" 
        ];
    }
}
