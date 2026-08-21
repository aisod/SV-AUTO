<!-- ════════════════ LABOR TRACKING SECTION ════════════════ -->
<!-- Add this section to add_job_card.php after the Parts Supply section -->

<style>
/* Reuse input styling from add_quotation.php without duplicating all quotation CSS */
.aq-ic{
    width:100%;
    border:1px solid #e5e7eb;
    border-radius:0.5rem;
    padding:.5rem .75rem;
    font-size:.875rem;
    outline:none;
}
.aq-ic:focus{
    box-shadow:0 0 0 2px rgba(249,115,22,.45);
    border-color:#fdba74;
}
</style>

<div class="jc-section no-print">Rate & Section Configuration</div>

<div style="padding:12px 14px;" class="no-print">
    
    <!-- Service Type -->
    <div class="jc-field">
        <label>Service Type</label>
        <select name="service_type" id="serviceType" onchange="toggleMobileFields()" required>
            <option value="in_shop">In-Shop Service</option>
            <option value="mobile">Mobile Service (Callout)</option>
        </select>
    </div>

    <!-- Work Date & Time -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
        <div class="jc-field">
            <label>Work Date</label>
            <input type="date" name="work_date" id="workDate" value="<?php echo date('Y-m-d'); ?>" required onchange="calculateLabor()">
        </div>
        <div class="jc-field">
            <label>Start Time</label>
            <input type="time" name="work_start_time" id="startTime" required onchange="calculateLabor()">
        </div>
        <div class="jc-field">
            <label>End Time</label>
            <input type="time" name="work_end_time" id="endTime" required onchange="calculateLabor()">
        </div>
    </div>

    <!-- RATES & SECTION CONFIGURATION (Quotation UI) -->
    <div class="bg-white rounded-xl border border-gray-200 mb-4 no-print">
        <div class="px-5 py-4 flex items-center justify-between text-sm font-semibold text-gray-700">
            <span class="flex items-center gap-2"><i class="fas fa-sliders-h text-orange-500"></i> Rate & Section Configuration</span>
            <!-- Keep behaviour parity: reset is still available -->
            <button type="button" onclick="resetRatesToDefault()" class="bg-gray-100 hover:bg-gray-200 text-gray-800 border border-gray-200 px-3 py-1 rounded text-xs font-semibold">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>

        <div class="px-5 pb-5 border-t border-gray-100 pt-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Normal Time Rate</label>
                    <input type="number" step="any" id="f-normal_time_rate" name="rate_normal" class="aq-ic w-full"
                           value="<?php echo $laborRates['normal_hours'] ?? 150; ?>" onchange="calculateLabor()"/>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Overtime Rate</label>
                    <input type="number" step="any" id="f-overtime_rate" name="rate_after" class="aq-ic w-full"
                           value="<?php echo $laborRates['after_hours'] ?? 225; ?>" onchange="calculateLabor()"/>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Public Holiday Rate</label>
                    <input type="number" step="any" id="f-public_holiday_rate" name="rate_holiday" class="aq-ic w-full"
                           value="<?php echo $laborRates['holiday'] ?? 450; ?>" onchange="calculateLabor()"/>
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">VAT Rate (%)</label>
                    <input type="number" step="any" id="jc-vat_rate_display" class="aq-ic w-full" value="15" disabled />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label class="block text-xs text-gray-600 mb-1">Activity 1 Label</label>
                    <input id="jc-attend_to_service_label" class="aq-ic w-full" value="Attend to service" disabled/>
                </div>
                <div><label class="block text-xs text-gray-600 mb-1">Activity 2 Label</label>
                    <input id="jc-diagnostic_label" class="aq-ic w-full" value="Diagnostic" disabled/>
                </div>
            </div>

            <div class="flex flex-wrap gap-6 mb-6 text-sm">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" id="jc-show_normal_time" class="accent-orange-500 w-4 h-4" checked disabled/> Show Normal Time
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" id="jc-show_overtime" class="accent-orange-500 w-4 h-4" checked disabled/> Show Overtime
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" id="jc-show_public_holiday" class="accent-orange-500 w-4 h-4" checked disabled/> Show Public Holiday
                </label>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Print blank PDF layout</h3>
                <p class="text-sm font-bold text-gray-800 mb-3">Print Blank only: these row counts apply when you click <strong>Print Blank</strong>.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                    <div><label class="block text-xs text-gray-600 mb-1">Normal time rows</label><input type="number" step="1" min="2" max="40" id="jc-blank_normal_lines" class="aq-ic w-full" value="5" disabled/></div>
                    <div><label class="block text-xs text-gray-600 mb-1">Overtime rows</label><input type="number" step="1" min="2" max="40" id="jc-blank_overtime_lines" class="aq-ic w-full" value="5" disabled/></div>
                    <div><label class="block text-xs text-gray-600 mb-1">Public holiday rows</label><input type="number" step="1" min="2" max="40" id="jc-blank_holiday_lines" class="aq-ic w-full" value="5" disabled/></div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-2 gap-3 mb-4">
                    <div><label class="block text-xs text-gray-600 mb-1">Blank parts rows</label><input type="number" step="1" min="1" max="40" id="jc-blank_parts_lines" class="aq-ic w-full" value="6" disabled/></div>
                    <div><label class="block text-xs text-gray-600 mb-1">Blank consumables rows</label><input type="number" step="1" min="0" max="40" id="jc-blank_cons_lines" class="aq-ic w-full" value="3" disabled/></div>
                </div>

                <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Preview + Print table layout</h3>
                <p class="text-sm font-bold text-gray-800 mb-3">These options apply to both <strong>Preview</strong> and <strong>Print Blank</strong>.</p>

                <div class="flex flex-wrap gap-x-8 gap-y-2 mb-3 text-sm">
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="jc-print_blank_show_labour" class="accent-orange-500 w-4 h-4" checked disabled/> Include labour table</label>
                    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="jc-print_blank_show_parts" class="accent-orange-500 w-4 h-4" checked disabled/> Include parts &amp; consumables table</label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm mb-4">
                    <fieldset class="min-w-0 border border-gray-200 rounded-lg p-3">
                        <legend class="text-xs font-semibold text-gray-700 px-1">Labour columns</legend>
                        <div class="flex flex-wrap gap-4 mt-2">
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="jc-blank_col_lab_hours" class="accent-orange-500 w-4 h-4" checked disabled/> Hours</label>
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="jc-blank_col_lab_rate" class="accent-orange-500 w-4 h-4" checked disabled/> Rate</label>
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="jc-blank_col_lab_total" class="accent-orange-500 w-4 h-4" checked disabled/> Line total</label>
                        </div>
                    </fieldset>
                    <fieldset class="min-w-0 border border-gray-200 rounded-lg p-3">
                        <legend class="text-xs font-semibold text-gray-700 px-1">Parts columns</legend>
                        <div class="flex flex-wrap gap-4 mt-2">
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="jc-blank_col_part_qty" class="accent-orange-500 w-4 h-4" checked disabled/> Qty</label>
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="jc-blank_col_part_cost" class="accent-orange-500 w-4 h-4" checked disabled/> Unit cost</label>
                            <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="jc-blank_col_part_total" class="accent-orange-500 w-4 h-4" checked disabled/> Line total</label>
                        </div>
                    </fieldset>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekend is treated as overtime everywhere (no separate weekend field). -->

    <!-- Mobile Service Fields (hidden by default) -->
    <div id="mobileFields" style="display:none;background:#FFF8F0;padding:12px;border-radius:8px;border:2px dashed var(--p);margin-bottom:12px;">
        <div style="font-size:13px;font-weight:700;color:var(--s);margin-bottom:8px;">
            <i class="fas fa-truck"></i> Mobile Service Details
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div class="jc-field">
                <label>Distance (km)</label>
                <input type="number" step="0.1" name="distance_km" id="distanceKm" value="0" onchange="calculateLabor()">
            </div>
            <div class="jc-field">
                <label>Location</label>
                <input type="text" name="service_location" placeholder="e.g., Klein Windhoek">
            </div>
        </div>
        
        <!-- EDITABLE MOBILE RATES -->
        <div style="background:white;padding:10px;border-radius:6px;border:1px solid #ddd;">
            <div style="font-size:12px;font-weight:700;color:var(--s);margin-bottom:8px;">
                Mobile Service Rates (Editable)
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;font-size:12px;">
                <div>
                    <label style="font-size:10px;font-weight:700;color:#555;display:block;margin-bottom:4px;">
                        Callout Fee (N$)
                    </label>
                    <input type="number" step="0.01" name="mobile_callout" id="mobileCallout" 
                        value="<?php echo $mobileRates['callout_fee'] ?? 500; ?>" 
                        onchange="calculateLabor()"
                        style="width:100%;padding:6px;border:2px solid #ddd;border-radius:4px;font-size:12px;">
                </div>
                <div>
                    <label style="font-size:10px;font-weight:700;color:#555;display:block;margin-bottom:4px;">
                        Per KM (N$)
                    </label>
                    <input type="number" step="0.01" name="mobile_per_km" id="mobilePerKm" 
                        value="<?php echo $mobileRates['per_km_rate'] ?? 15; ?>" 
                        onchange="calculateLabor()"
                        style="width:100%;padding:6px;border:2px solid #ddd;border-radius:4px;font-size:12px;">
                </div>
                <div>
                    <label style="font-size:10px;font-weight:700;color:#555;display:block;margin-bottom:4px;">
                        Min Distance (km)
                    </label>
                    <input type="number" step="0.1" name="mobile_min_dist" id="mobileMinDist" 
                        value="<?php echo $mobileRates['min_callout_distance'] ?? 5; ?>" 
                        onchange="calculateLabor()"
                        style="width:100%;padding:6px;border:2px solid #ddd;border-radius:4px;font-size:12px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Labor Cost Summary (Auto-calculated) -->
    <div style="background:#f0f0f0;padding:12px;border-radius:8px;border:2px solid #ddd;">
        <div style="font-size:13px;font-weight:700;color:var(--s);margin-bottom:8px;">
            <i class="fas fa-calculator"></i> Labor Cost Summary (Auto-calculated)
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;">
            <div>
                <strong>Total Hours:</strong> <span id="displayHours">0.00</span> hours
            </div>
            <div>
                <strong>Rate Applied:</strong> N$<span id="displayRate">0.00</span>/hour
            </div>
            <div>
                <strong>Rate Type:</strong> <span id="displayRateType">—</span>
            </div>
            <div>
                <strong>Labor Cost:</strong> N$<span id="displayLaborCost">0.00</span>
            </div>
        </div>

        <div id="mobileCostDisplay" style="display:none;margin-top:8px;padding-top:8px;border-top:1px solid #ddd;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;">
                <div>
                    <strong>Callout Fee:</strong> N$<span id="displayCallout">0.00</span>
                </div>
                <div>
                    <strong>Travel Cost:</strong> N$<span id="displayTravel">0.00</span>
                </div>
            </div>
        </div>

        <div style="margin-top:8px;padding-top:8px;border-top:2px solid var(--p);font-size:14px;font-weight:900;color:var(--p);">
            <strong>TOTAL LABOR & TRAVEL:</strong> N$<span id="displayTotal">0.00</span>
        </div>
    </div>

    <!-- Hidden fields to store calculated values -->
    <input type="hidden" name="total_hours" id="totalHours" value="0">
    <input type="hidden" name="labor_rate_applied" id="laborRate" value="0">
    <input type="hidden" name="labor_cost" id="laborCost" value="0">
    <input type="hidden" name="callout_fee" id="calloutFee" value="0">
    <input type="hidden" name="travel_cost" id="travelCost" value="0">

</div>

<script>
// Store default rates for reset function
const defaultRates = {
    normal: <?php echo $laborRates['normal_hours'] ?? 150; ?>,
    after: <?php echo $laborRates['after_hours'] ?? 225; ?>,
    holiday: <?php echo $laborRates['holiday'] ?? 450; ?>,
    callout: <?php echo $mobileRates['callout_fee'] ?? 500; ?>,
    perKm: <?php echo $mobileRates['per_km_rate'] ?? 15; ?>,
    minDist: <?php echo $mobileRates['min_callout_distance'] ?? 5; ?>
};

const holidays = <?php
    try {
        $stmt = $pdo->query("SELECT holiday_date FROM public_holidays WHERE is_active = 1");
        $dates = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dates[] = $row['holiday_date'];
        }
        echo json_encode($dates);
    } catch (Exception $e) {
        echo json_encode([]);
    }
?>;

function resetRatesToDefault() {
    const normalEl=document.getElementById('f-normal_time_rate');
    const overtimeEl=document.getElementById('f-overtime_rate');
    const holidayEl=document.getElementById('f-public_holiday_rate');
    if(normalEl) normalEl.value = defaultRates.normal;
    if(overtimeEl) overtimeEl.value = defaultRates.after;
    if(holidayEl) holidayEl.value = defaultRates.holiday;
    document.getElementById('mobileCallout').value = defaultRates.callout;
    document.getElementById('mobilePerKm').value = defaultRates.perKm;
    document.getElementById('mobileMinDist').value = defaultRates.minDist;
    calculateLabor();
}

function toggleMobileFields() {
    const serviceType = document.getElementById('serviceType').value;
    const mobileFields = document.getElementById('mobileFields');
    const mobileCostDisplay = document.getElementById('mobileCostDisplay');
    
    if (serviceType === 'mobile') {
        mobileFields.style.display = 'block';
        mobileCostDisplay.style.display = 'block';
    } else {
        mobileFields.style.display = 'none';
        mobileCostDisplay.style.display = 'none';
        document.getElementById('distanceKm').value = 0;
    }
    
    calculateLabor();
}

function calculateLabor() {
    const workDate = document.getElementById('workDate').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;
    const serviceType = document.getElementById('serviceType').value;
    const distanceKm = parseFloat(document.getElementById('distanceKm').value) || 0;
    
    if (!workDate || !startTime || !endTime) {
        return;
    }
    
    // Calculate hours
    const start = new Date(`${workDate}T${startTime}`);
    const end = new Date(`${workDate}T${endTime}`);
    let hours = (end - start) / (1000 * 60 * 60);
    
    if (hours < 0) hours += 24;
    hours = Math.round(hours * 100) / 100;
    
    // Get EDITABLE rates from form
    const rates = {
        normal_hours: parseFloat(document.getElementById('f-normal_time_rate').value) || 0,
        after_hours: parseFloat(document.getElementById('f-overtime_rate').value) || 0,
        holiday: parseFloat(document.getElementById('f-public_holiday_rate').value) || 0
    };
    
    // Determine rate type
    const rateInfo = determineRate(workDate, startTime, rates);
    const hourlyRate = rateInfo.rate;
    const rateType = rateInfo.type;
    const laborCost = hours * hourlyRate;
    
    // Calculate mobile costs with EDITABLE rates
    let calloutFee = 0;
    let travelCost = 0;
    
    if (serviceType === 'mobile') {
        const mobileCallout = parseFloat(document.getElementById('mobileCallout').value) || 0;
        const mobilePerKm = parseFloat(document.getElementById('mobilePerKm').value) || 0;
        const mobileMinDist = parseFloat(document.getElementById('mobileMinDist').value) || 0;
        
        if (distanceKm >= mobileMinDist) {
            calloutFee = mobileCallout;
        }
        travelCost = distanceKm * mobilePerKm;
    }
    
    const totalCost = laborCost + calloutFee + travelCost;
    
    // Update display
    document.getElementById('displayHours').textContent = hours.toFixed(2);
    document.getElementById('displayRate').textContent = hourlyRate.toFixed(2);
    document.getElementById('displayRateType').textContent = rateType;
    document.getElementById('displayLaborCost').textContent = laborCost.toFixed(2);
    document.getElementById('displayCallout').textContent = calloutFee.toFixed(2);
    document.getElementById('displayTravel').textContent = travelCost.toFixed(2);
    document.getElementById('displayTotal').textContent = totalCost.toFixed(2);
    
    // Update hidden fields
    document.getElementById('totalHours').value = hours.toFixed(2);
    document.getElementById('laborRate').value = hourlyRate.toFixed(2);
    document.getElementById('laborCost').value = laborCost.toFixed(2);
    document.getElementById('calloutFee').value = calloutFee.toFixed(2);
    document.getElementById('travelCost').value = travelCost.toFixed(2);
}

function determineRate(dateStr, timeStr, rates) {
    // Check if holiday
    if (holidays.includes(dateStr)) {
        return { rate: rates.holiday, type: 'Public Holiday' };
    }
    
    // Check if weekend: treat weekend as overtime.
    const date = new Date(dateStr);
    const dayOfWeek = date.getDay();
    if (dayOfWeek === 0 || dayOfWeek === 6) {
        return { rate: rates.after_hours, type: 'Overtime (Weekend)' };
    }
    
    // Check if after hours
    const hour = parseInt(timeStr.split(':')[0]);
    if (hour < 8 || hour >= 17) {
        return { rate: rates.after_hours, type: 'After Hours' };
    }
    
    // Normal hours
    return { rate: rates.normal_hours, type: 'Normal Hours' };
}

// Calculate on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateLabor();
});
</script>
