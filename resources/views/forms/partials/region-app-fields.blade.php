<div class="form-group">
    <label class="form-label" for="dataRegion">
        Data Region <span class="required-star">*</span>
    </label>
    <select id="dataRegion" name="data_region" required class="form-control">
        <option value="" disabled selected>Select</option>
        @foreach ($regions as $region)
            <option value="{{ $region->id }}">{{ $region->name }}</option>
        @endforeach
    </select>
</div>

<!-- Experience dropdown - Populated dynamically based on region selection -->
<div class="form-group" id="appContainer" style="display: none;">
    <label class="form-label" for="selectedApp">
        Experience <span class="required-star">*</span>
    </label>
    <select id="selectedApp" name="trial_app" class="form-control">
        <option value="" disabled selected>Select</option>
    </select>
</div>
