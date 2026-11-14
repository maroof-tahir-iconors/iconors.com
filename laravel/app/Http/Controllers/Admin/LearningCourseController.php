<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Admin → Learning Center: courses shown to contributors. */
class LearningCourseController extends Controller
{
    public function index(Request $request)
    {
        $this->gate($request);

        return view('admin.learning.index', [
            'courses' => LearningCourse::orderBy('section')->orderBy('sort_order')->orderBy('id')->get(),
            'sections' => LearningCourse::distinct()->orderBy('section')->pluck('section'),
        ]);
    }

    public function store(Request $request)
    {
        $this->gate($request);
        LearningCourse::create($this->data($request) + ['sort_order' => (int) LearningCourse::max('sort_order') + 1]);

        return back()->with('status', 'Course added.');
    }

    public function update(Request $request, LearningCourse $course)
    {
        $this->gate($request);
        $data = $this->data($request, $course);
        $course->update($data);

        return back()->with('status', 'Course updated.');
    }

    public function destroy(Request $request, LearningCourse $course)
    {
        $this->gate($request);
        if ($course->thumbnail) {
            Storage::disk('public')->delete($course->thumbnail);
        }
        $course->delete();

        return back()->with('status', 'Course removed.');
    }

    private function data(Request $request, ?LearningCourse $course = null): array
    {
        $v = $request->validate([
            'section' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:500'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $out = [
            'section' => $v['section'], 'title' => $v['title'], 'author' => $v['author'] ?? null, 'url' => $v['url'],
            'color' => $v['color'] ?? '#5a01f0', 'is_active' => (bool) ($v['is_active'] ?? true),
        ];
        if (isset($v['sort_order'])) {
            $out['sort_order'] = (int) $v['sort_order'];
        }
        if ($request->hasFile('thumbnail')) {
            if ($course?->thumbnail) {
                Storage::disk('public')->delete($course->thumbnail);
            }
            $out['thumbnail'] = $request->file('thumbnail')->store('learning', 'public');
        }

        return $out;
    }

    private function gate(Request $request): void
    {
        abort_unless($request->user()?->can('settings.manage') || $request->user()?->can('contributors.view'), 403);
    }
}
