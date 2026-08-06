<div class="form-group">
    <label class="control-label">Name</label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $addon?->name) }}" required>
</div>
<div class="row">
    <div class="col-md-6 form-group">
        <label class="control-label">Author</label>
        <input type="text" name="author" class="form-control" value="{{ old('author', $addon?->author) }}">
    </div>
    <div class="col-md-6 form-group">
        <label class="control-label">Category</label>
        <select name="category" class="form-control">
            @foreach (['Plugin', 'Mod', 'Datapack'] as $cat)
                <option value="{{ $cat }}" @if (old('category', $addon?->category ?? 'Plugin') === $cat) selected @endif>{{ $cat }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="row">
    <div class="col-md-4 form-group">
        <label class="control-label">Release source</label>
        <select name="source" class="form-control">
            @foreach (['static' => 'Static link (one version)', 'modrinth' => 'Modrinth', 'github' => 'GitHub Releases', 'hangar' => 'Hangar (PaperMC)'] as $value => $label)
                <option value="{{ $value }}" @if (old('source', $addon?->source ?? 'static') === $value) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
        <p class="text-muted small">Anything other than "Static link" gives users an <strong>Available versions</strong> picker.</p>
    </div>
    <div class="col-md-8 form-group">
        <label class="control-label">Project ID</label>
        <input type="text" name="source_id" class="form-control" value="{{ old('source_id', $addon?->source_id) }}" placeholder="luckperms  |  EssentialsX/Essentials">
        <p class="text-muted small">
            Modrinth: project slug (<code>chunky</code>) &middot; GitHub: <code>owner/repo</code> &middot; Hangar: project name.
            Leave empty for a static link.
        </p>
    </div>
</div>
<div class="form-group">
    <label class="control-label">Download URL</label>
    <input type="text" name="url" class="form-control" value="{{ old('url', $addon?->url) }}" placeholder="https://github.com/owner/repo/releases/download/v1.0/file.jar">
    <p class="text-muted small">
        Required for the static source only. Direct link to the file, must be reachable from the node
        (test with <code>curl -IL &lt;url&gt;</code>). With Modrinth/GitHub/Hangar the link is resolved per
        version at install time, so this field can stay empty.
    </p>
</div>
<div class="row">
    <div class="col-md-5 form-group">
        <label class="control-label">Saved filename</label>
        <input type="text" name="filename" class="form-control" value="{{ old('filename', $addon?->filename) }}" placeholder="Vault.jar" required>
    </div>
    <div class="col-md-4 form-group">
        <label class="control-label">Version</label>
        <input type="text" name="version" class="form-control" value="{{ old('version', $addon?->version) }}" placeholder="1.7.3">
    </div>
    <div class="col-md-3 form-group">
        <label class="control-label">Rating</label>
        <input type="text" name="rating" class="form-control" value="{{ old('rating', $addon?->rating ?? '5.0') }}">
    </div>
</div>
<div class="row">
    <div class="col-md-4 form-group">
        <label class="control-label">Downloads label</label>
        <input type="text" name="downloads" class="form-control" value="{{ old('downloads', $addon?->downloads) }}" placeholder="51.5M">
    </div>
    <div class="col-md-8 form-group">
        <label class="control-label">Description</label>
        <input type="text" name="description" class="form-control" value="{{ old('description', $addon?->description) }}">
    </div>
</div>
