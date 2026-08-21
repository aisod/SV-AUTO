<!-- ════════════════ LABOR TRACKING SECTION (EDIT MODE) ════════════════ -->

<div class="jc-section">LABOR & SERVICE DETAILS</div>

<div style="padding:12px 14px;">
    
    <!-- Service Type -->
    <div class="jc-field">
        <label>Service Type</label>
        <select name="service_type" id="serviceType" onchange="toggleMobileFields()" required>
            <option value="in_shop" <?php echo ($job_card['service_type'] ?? 'in_shop') === 'in_shop' ? 'selected' : ''; ?>>In-Shop Service</option>
            <option value="mobile" <?php echo ($job_card['service_type'] ?? '') === 'mobile' ? 'selected' : ''; ?>>Mobile Service (Callout)</option>
        </select>
    </div>

    <!-- Work Date & Time -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
        <div class="jc-field">
            <label>Work Date</label>
            <input type="date" name="work_date" id="workDate" value="<?php echo htmlspecialchars($job_card['work_date'] ?? date('Y-m-d')); ?>" required onchange="calculateLabor()">
        </div>
        <div class="jc-field">
            <label>Start Time</label>
            <input type="time" name="work_start_time" id="startTime" value="<?php echo htmlspecialchars($job_card['work_start_time'] ?? ''); ?>" required onchange="calculateLabor()">
        </div>
        <div class="jc-field">
            <label>End Time</label>
            <input type="time" name="work_end_time" id="endTime" value="<?php echo htmlspecialchars($job_card['work_end_time'] ?? ''); ?>" required onchange="calculateLabor()">
        </div>
    </div>

    <!-- EDITABLE RATES SECTION -->
    <div style="background:#FFF8F0;padding:12px;border-radius:8px;border:2px solid var(--p);margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <div style="font-size:13px;font-weight:700;color:var(--s);">
                <i class="fas fa-dollar-sign"></i> Labor Rates (Editable)
            </div>
            <button type="button" onclick="resetRatesToDefault()" style="background:#666;color:white;border:none;padding:6px 12px;border-radius:6px;font-size:11px;cursor:pointer;">
                <i class="fas fa-undo"></i> Reset to Default
            </button>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12px;">
            <div>
                <label style="font-size:11px;font-weight:700;color:#555;display:block;margin-bottom:4px;">
                    Normal Hours (N$/hour)
                </label>
                <input type="number" step="0.01" name="rate_normal" id="rateNormal" 
                    value="<?php echo $laborRates['normal_hours'] ?? 150; ?>" 
                    onchange="calculateLabor()"
                    style="width:100%;padding:8px;border:2px solid #ddd;border-radius:6px;font-size:13px;">
            </div>
            <div>
                <label style="font-size:11px;font-weight:700;color:#555;display:block;margin-bottom:4px;">
                    After Hours (N$/hour)
                </label>
                <input type="number" step="0.01" name="rate_after" id="rateAfter" 
                    value="<?php echo $laborRates['after_hours'] ?? 225; ?>" 
                    onchange="calculateLabor()"
                    style="width:100%;padding:8px;border:2px solid #ddd;border-radius:6px;font-size:13px;">
            </div>
            <div>
                <label style="font-size:11px;font-weight:700;color:#555;display:block;margin-bottom:4px;">
                    Weekend (N$/hour)
                </label>
                <input type="number" step="0.01" name="rate_weekend" id="rateWeekend" 
                    value="<?php echo $laborRates['weekend'] ?? 300; ?>" 
                    onchange="calculateLabor()"
                    style="width:100%;padding:8px;border:2px solid #ddd;border-radius:6px;font-size:13px;">
            </div>
            <div>
                <label style="font-size:11px;font-weight:700;color:#555;display:block;margin-bottom:4px;">
                    Holiday (N$/hour)
                </label>
                <input type="number" step="0.01" name="rate_holiday" id="rateHoliday" 
                    value="<?php echo $laborRates['holiday'] ?? 450; ?>" 
                    onchange="calculateLabor()"
                    style="width:100%;padding:8px;border:2px solid #ddd;border-radius:6px;font-size:13px;">
            </div>
        </div>
    </div>

    <!-- Mobile Service Fields -->
    <div id="mobileFields" style="display:<?php echo ($job_card['service_type'] ?? '') === 'mobile' ? 'block' : 'none'; ?>;background:#FFF8F0;padding:12px;border-radius:8px;border:2px dashed var(--p);margin-bottom:12px;">
        <div style="font-size:13px;font-weight:700;color:var(--s);margin-bottom:8px;">
            <i class="fas fa-truck"></i> Mobile Service Details
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div class="jc-field">
                <label>Distance (km)</label>
                <input type="number" step="0.1" name="distance_km" id="distanceKm" value="<?php echo htmlspecialchars($job_card['distance_km'] ?? 0); ?>" onchange="calculateLabor()">
            </div>
            <div class="jc-field">
                <label>Location</label>
                <input type="text" name="service_location" value="<?php echo htmlspecialchars($extra_data['service_location'] ?? ''); ?>" placeholder="e.g., Klein Windhoek">
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
                <strong>Total Hours:</strong> <span id="displayHours"><?php echo number_format($job_card['total_hours'] ?? 0, 2); ?></span> hours
            </div>
            <div>
                <strong>Rate Applied:</strong> N$<span id="displayRate"><?php echo number_format($job_card['labor_rate_applied'] ?? 0, 2); ?></span>/hour
            </div>
            <div>
                <strong>Rate Type:</strong> <span id="displayRateType">—</span>
            </div>
            <div>
                <strong>Labor Cost:</strong> N$<span id="displayLaborCost"><?php echo number_format($job_card['labor_cost'] ?? 0, 2); ?></span>
            </div>
        </div>

        <div id="mobileCostDisplay" style="display:<?php echo ($job_card['service_type'] ?? '') === 'mobile' ? 'block' : 'none'; ?>;margin-top:8px;padding-top:8px;border-top:1px solid #ddd;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;">
                <div>
                    <strong>Callout Fee:</strong> N$<span id="displayCallout"><?php echo number_format($job_card['callout_fee'] ?? 0, 2); ?></span>
                </div>
                <div>
                    <strong>Travel Cost:</strong> N$<span id="displayTravel"><?php echo number_format($job_card['travel_cost'] ?? 0, 2); ?></span>
                </div>
            </div>
        </div>

        <div style="margin-top:8px;padding-top:8px;border-top:2px solid var(--p);font-size:14px;font-weight:900;color:var(--p);">
            <strong>TOTAL LABOR & TRAVEL:</strong> N$<span id="displayTotal"><?php echo number_format(($job_card['labor_cost'] ?? 0) + ($job_card['callout_fee'] ?? 0) + ($job_card['travel_cost'] ?? 0), 2); ?></span>
        </div>
    </div>

    <!-- Hidden fields to store calculated values -->
    <input type="hidden" name="total_hours" id="totalHours" value="<?php echo $job_card['total_hours'] ?? 0; ?>">
    <input type="hidden" name="labor_rate_applied" id="laborRate" value="<?php echo $job_card['labor_rate_applied'] ?? 0; ?>">
    <input type="hidden" name="labor_cost" id="laborCost" value="<?php echo $job_card['labor_cost'] ?? 0; ?>">
    <input type="hidden" name="callout_fee" id="calloutFee" value="<?php echo $job_card['callout_fee'] ?? 0; ?>">
    <input type="hidden" name="travel_cost" id="travelCost" value="<?php echo $job_card['travel_cost'] ?? 0; ?>">

</div>

<script>
// Store default rates for reset function
const defaultRates = {
    normal: <?php echo $laborRates['normal_hours'] ?? 150; ?>,
    after: <?php echo $laborRates['after_hours'] ?? 225; ?>,
    weekend: <?php echo $laborRates['weekend'] ?? 300; ?>,
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
    document.getElementById('rateNormal').value = defaultRates.normal;
    document.getElementById('rateAfter').value = defaultRates.after;
    document.getElementById('rateWeekend').value = defaultRates.weekend;
    document.getElementById('rateHoliday').value = defaultRates.holiday;
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
        normal_hours: parseFloat(document.getElementById('rateNormal').value) || 0,
        after_hours: parseFloat(document.getElementById('rateAfter').value) || 0,
        weekend: parseFloat(document.getElementById('rateWeekend').value) || 0,
        holiday: parseFloat(document.getElementById('rateHoliday').value) || 0
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
    
    // Check if weekend
    const date = new Date(dateStr);
    const dayOfWeek = date.getDay();
    if (dayOfWeek === 0 || dayOfWeek === 6) {
        return { rate: rates.weekend, type: 'Weekend' };
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
