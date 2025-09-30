@extends('layouts.app')

@section('styles')
<style>
#imageInputWrapper{min-height:150px;border:2px dashed #007bff;border-radius:8px;background:#f8f9fa;display:flex;align-items:center;justify-content:center;cursor:pointer;padding:10px}
#imageInputWrapper.dragover{background:#007bff;color:#fff}
.thumb{max-height:120px;border-radius:6px}
.row-thumbs .col{margin-bottom:12px}
</style>
@endsection

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="container mt-4">
  <h3>Chunked Image Upload (resume, checksum, variants)</h3>

  <label id="imageInputWrapper">
    <div class="text-center">
      <strong>Drag & drop image here or click</strong><br>
      <small>Files: images only. Large files supported by chunking.</small>
    </div>
    <input id="imageInput" type="file" multiple accept="image/*" style="display:none">
  </label>

  <div id="status" class="mt-3"></div>

  <div class="row row-thumbs mt-3" id="thumbs"></div>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$.ajaxSetup({ headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')} });

const CHUNK_SIZE = 1024*1024; // 1MB

$('#imageInputWrapper').on('click', ()=>$('#imageInput').click());
$('#imageInputWrapper').on('dragover', e=>{ e.preventDefault(); $('#imageInputWrapper').addClass('dragover'); });
$('#imageInputWrapper').on('dragleave', e=>{ e.preventDefault(); $('#imageInputWrapper').removeClass('dragover'); });
$('#imageInputWrapper').on('drop', e=>{ e.preventDefault(); $('#imageInputWrapper').removeClass('dragover'); handleFiles(e.originalEvent.dataTransfer.files); });

$('#imageInput').on('change', function(){ handleFiles(this.files); });

function bufToHex(buffer){
  const hexCodes=[];
  const view=new DataView(buffer);
  for(let i=0;i<view.byteLength;i+=4){
    const value=view.getUint32(i);
    const stringValue = value.toString(16);
    const padding = '00000000';
    const padded = (padding + stringValue).slice(-padding.length);
    hexCodes.push(padded);
  }
  return hexCodes.join('');
}

async function sha256Hex(file){
  const arrayBuffer = await file.arrayBuffer();
  const hashBuffer = await crypto.subtle.digest('SHA-256', arrayBuffer);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  const hex = hashArray.map(b=>b.toString(16).padStart(2,'0')).join('');
  return hex;
}

async function handleFiles(files){
  for(const file of files){
    if(!file.type.startsWith('image/')) continue;

    const uploadId = crypto.randomUUID();
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    const checksum = await sha256Hex(file);

    $('#status').prepend(`<div id="s_${uploadId}">Preparing upload: ${file.name}</div>`);
    // ask server which chunks exist
    const resp = await $.post("{{ route('upload.status') }}", { upload_id: uploadId });
    const existing = resp.chunks || [];

    for(let i=0;i<totalChunks;i++){
      if(existing.includes(i)) { $('#s_'+uploadId).append(`<div>chunk ${i} already present</div>`); continue; }
      const start = i*CHUNK_SIZE;
      const end = Math.min(start+CHUNK_SIZE, file.size);
      const chunk = file.slice(start,end);
      const fd = new FormData();
      fd.append('upload_id', uploadId);
      fd.append('chunk_index', i);
      fd.append('total_chunks', totalChunks);
      fd.append('file', chunk);
      await $.ajax({
        url: "{{ route('upload.chunk') }}",
        method: 'POST',
        data: fd,
        processData:false, contentType:false
      });
      $('#s_'+uploadId).append(`<div>uploaded chunk ${i}</div>`);
    }

    // request assembly & checksum verification
    const res = await $.post("{{ route('upload.complete') }}", { upload_id: uploadId, checksum: checksum });
    if(res.status === 'ok'){
      $('#s_'+uploadId).append('<div class="text-success">Completed & queued for variants</div>');
      $('#thumbs').prepend(`
        <div class="col-3">
          <img src="${res.path}" class="thumb img-fluid">
          <div class="mt-1"><button class="btn btn-sm btn-primary attach" data-image="${res.image_id}">Attach to product</button></div>
        </div>`);
    } else {
      $('#s_'+uploadId).append(`<div class="text-danger">Failed: ${JSON.stringify(res)}</div>`);
    }
  }
}

// attach example
$(document).on('click','.attach', function(){
  const imageId = $(this).data('image');
  const sku = prompt('Enter product SKU to attach this image to (will create if missing):');
  if(!sku) return;
  $.post("{{ route('upload.attach') }}", { image_id:imageId, product_sku:sku, make_primary: true })
    .done(j=>alert('Attached: '+JSON.stringify(j)))
    .fail(e=>alert('Attach failed: '+JSON.stringify(e.responseText)));
});
</script>
@endsection
