@extends('layouts.app')
@section('styles')
<style>
#dropzone {
    min-height: 150px;
    border: 2px dashed #007bff;
    border-radius: 8px;
    background-color: #f8f9fa;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    transition: background-color 0.3s, color 0.3s;
}
#dropzone.bg-hover {
    background-color: #007bff;
    color: #fff;
}
#uploadedImages img {
    max-height: 150px;
    margin-bottom: 10px;
}
.image-wrapper {
    position: relative;
    display: inline-block;
    margin: 5px;
}
.image-wrapper .remove-btn {
    position: absolute;
    top: 2px;
    right: 2px;
    background: rgba(255,0,0,0.8);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    cursor: pointer;
    font-size: 12px;
}
</style>
@endsection
@section('content')
<div class="container mt-5">
    <h2 class="mb-4 text-primary">Bulk CSV Import & Image Upload</h2>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-info text-white">CSV Import</div>
        <div class="card-body">
            <form id="csvImportForm" enctype="multipart/form-data">
                @csrf
                <input type="file" name="csv" id="csvFile" class="form-control mb-2" accept=".csv" required>
                <button class="btn btn-success">Import CSV</button>
            </form>
            <div id="csvResult" class="mt-3"></div>
        </div>
    </div>

    
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$('#csvImportForm').submit(function(e){
    e.preventDefault();
    let formData = new FormData(this);
    $.ajax({
        url: "{{ route('import.csv') }}",
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res){
            $('#csvResult').html(`<div class="alert alert-info">
                Total: ${res.total}, Imported: ${res.imported}, Updated: ${res.updated}, Invalid: ${res.invalid}, Duplicates: ${res.duplicates}
            </div>`);
        }
    });
});




</script>
@endsection
