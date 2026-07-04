{{--
    Reusable permission picker.
    Expects:
      $permissionGroups : array<string, list<App\Enums\PartnerPermission>>
      $idPrefix         : string  (unique per form, e.g. 'add' / 'edit')
      $selected         : list<string> (keys to pre-check) — optional
--}}
@php($selected = $selected ?? [])

<div class="space-y-4">
    @foreach($permissionGroups as $group => $permissions)
        <div>
            <h4 class="text-xs font-bold uppercase tracking-wide text-secondary mb-2">{{ $group }}</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($permissions as $permission)
                    <label class="flex items-start gap-2 p-2 rounded border border-border-subtle cursor-pointer hover:bg-hover-subtle transition-colors">
                        <input type="checkbox"
                               name="permissions[]"
                               value="{{ $permission->value }}"
                               id="{{ $idPrefix }}_{{ \Illuminate\Support\Str::slug($permission->value) }}"
                               class="mt-1 {{ $idPrefix }}-perm-checkbox"
                               {{ in_array($permission->value, $selected, true) ? 'checked' : '' }}>
                        <span class="text-sm leading-tight">
                            <span class="font-medium" style="color: var(--color-text);">
                                {{ $permission->label() }}
                            </span>
                            @if($permission->isSensitive())
                                <span class="ml-1 text-[10px] px-1 py-0.5 rounded align-middle"
                                      style="background: var(--color-warning-bg); color: var(--color-warning);">
                                    sensible
                                </span>
                            @endif
                            <span class="block text-xs text-secondary mt-0.5">{{ $permission->description() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
