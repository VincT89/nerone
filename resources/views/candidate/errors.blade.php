@if($errors->any())<div class="form-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(session('saved'))<p class="saved-message" role="status">{{ session('saved') }}</p>@endif
@if(session('sent'))<p class="saved-message" role="status">{{ session('sent') }}</p>@endif
