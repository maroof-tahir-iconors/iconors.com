@extends('layouts.admin')

@section('title', 'Learning Center')
@section('subtitle', 'Courses and guides shown to contributors in their dashboard. Links open in a new tab.')

@section('content')
<datalist id="learnSections">@foreach ($sections as $s)<option value="{{ $s }}">@endforeach</datalist>

<div class="ico-card p-3 mb-4">
    <h2 class="h6 mb-3">Add a course</h2>
    <form method="POST" action="{{ route('admin.learning.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-2"><label class="form-label small">Section</label><input class="form-control form-control-sm" name="section" list="learnSections" required maxlength="120" placeholder="e.g. Icons"></div>
        <div class="col-md-3"><label class="form-label small">Title</label><input class="form-control form-control-sm" name="title" required maxlength="200"></div>
        <div class="col-md-2"><label class="form-label small">Author</label><input class="form-control form-control-sm" name="author" maxlength="120"></div>
        <div class="col-md-3"><label class="form-label small">URL</label><input class="form-control form-control-sm" name="url" type="url" required maxlength="500" placeholder="https://"></div>
        <div class="col-md-1"><label class="form-label small">Colour</label><input class="form-control form-control-sm form-control-color w-100" name="color" type="color" value="#5a01f0"></div>
        <div class="col-md-1"><input type="hidden" name="is_active" value="1"><button class="btn btn-ico-primary btn-sm w-100" type="submit">Add</button></div>
        <div class="col-md-4"><label class="form-label small">Thumbnail (optional, JPG/PNG/WebP, max 2 MB)</label><input class="form-control form-control-sm" name="thumbnail" type="file" accept="image/png,image/jpeg,image/webp"></div>
    </form>
</div>

<div class="ico-card p-3">
    @if ($courses->isEmpty())
        <p class="mb-0" style="color:var(--ico-muted)">No courses yet.</p>
    @else
        <div class="table-responsive">
            <table class="admin-table">
                <thead><tr><th style="width:60px"></th><th>Section</th><th>Title / Author / URL</th><th style="width:90px">Order</th><th style="width:80px">Active</th><th style="width:170px">Action</th></tr></thead>
                <tbody>
                @foreach ($courses as $c)
                    @php $fid = 'lc'.$c->id; @endphp
                    <tr>
                        <td>
                            <div style="width:52px;height:34px;border-radius:6px;overflow:hidden;background:linear-gradient(135deg, {{ $c->color ?: '#5a01f0' }}, #0b0a1a)">
                                @if ($c->thumbnail)<img src="{{ Storage::disk('public')->url($c->thumbnail) }}" alt="" style="width:100%;height:100%;object-fit:cover">@endif
                            </div>
                        </td>
                        <td><input form="{{ $fid }}" class="form-control form-control-sm" name="section" list="learnSections" value="{{ $c->section }}" required maxlength="120"></td>
                        <td>
                            <input form="{{ $fid }}" class="form-control form-control-sm mb-1" name="title" value="{{ $c->title }}" required maxlength="200">
                            <div class="d-flex gap-1">
                                <input form="{{ $fid }}" class="form-control form-control-sm" name="author" value="{{ $c->author }}" placeholder="Author" maxlength="120" style="max-width:160px">
                                <input form="{{ $fid }}" class="form-control form-control-sm" name="url" type="url" value="{{ $c->url }}" required maxlength="500">
                                <input form="{{ $fid }}" class="form-control form-control-sm form-control-color" name="color" type="color" value="{{ $c->color ?: '#5a01f0' }}" style="width:42px">
                            </div>
                            <input form="{{ $fid }}" class="form-control form-control-sm mt-1" name="thumbnail" type="file" accept="image/png,image/jpeg,image/webp" aria-label="Replace thumbnail">
                        </td>
                        <td><input form="{{ $fid }}" class="form-control form-control-sm" name="sort_order" type="number" min="0" value="{{ $c->sort_order }}"></td>
                        <td>
                            <input form="{{ $fid }}" type="hidden" name="is_active" value="0">
                            <input form="{{ $fid }}" class="form-check-input" type="checkbox" name="is_active" value="1" @checked($c->is_active) aria-label="Active">
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <form id="{{ $fid }}" method="POST" action="{{ route('admin.learning.update', $c) }}" enctype="multipart/form-data">@csrf @method('PUT')
                                    <button class="btn btn-ico-primary btn-sm" type="submit">Save</button>
                                </form>
                                <form method="POST" action="{{ route('admin.learning.destroy', $c) }}" onsubmit="return confirm('Remove this course?')">@csrf @method('DELETE')
                                    <button class="btn btn-ico-outline btn-sm" type="submit">Delete</button>
                                </form>
                                <a class="btn btn-ico-outline btn-sm" href="{{ $c->url }}" target="_blank" rel="noopener" title="Open"><i class="bi bi-box-arrow-up-right"></i></a>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
