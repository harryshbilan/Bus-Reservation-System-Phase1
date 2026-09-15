<?php if (!defined('BUSWAY_APP')) { http_response_code(403); exit('Forbidden'); } ?>
</main>

<div id="support-modal" class="modal-overlay" onclick="if(event.target===this) this.classList.remove('open')">
  <div class="modal-box support" onclick="event.stopPropagation()">
    <div class="modal-head support">
      <span>SUPPORT &amp; INFORMATION</span>
      <button type="button" onclick="document.getElementById('support-modal').classList.remove('open')">&#10005;</button>
    </div>
    <div class="modal-body">
      <div class="support-item">
        <div class="sicon">&#128222;</div>
        <div>
          <div class="stitle">HOTLINE</div>
          <div class="sline">09163248858</div>
          <div class="sline">Available 24/7 for booking &amp; emergencies</div>
        </div>
      </div>
      <div class="support-item">
        <div class="sicon">&#128231;</div>
        <div>
          <div class="stitle">EMAIL SUPPORT</div>
          <div class="sline">harrysh.bilan@neu.edu.ph</div>
          <div class="sline">Response within 24 hours on business days</div>
        </div>
      </div>
      <div class="support-item">
        <div class="sicon">&#128205;</div>
        <div>
          <div class="stitle">MAIN OFFICE</div>
          <div class="sline">9 Central Ave, New Era (Constitution Hills)</div>
          <div class="sline">Quezon City, 1107 Metro Manila</div>
        </div>
      </div>
      <div class="support-item">
        <div class="sicon">&#128336;</div>
        <div>
          <div class="stitle">OFFICE HOURS</div>
          <div class="sline">Monday – Saturday: 6:00 AM – 10:00 PM</div>
          <div class="sline">Sunday &amp; Holidays: 7:00 AM – 8:00 PM</div>
        </div>
      </div>
      <div class="support-item">
        <div class="sicon">&#8505;&#65039;</div>
        <div>
          <div class="stitle">BOOKING POLICY</div>
          <div class="sline">Tickets are non-refundable but may be rescheduled up to 2 hours before departure.</div>
          <div class="sline">Valid government-issued ID required upon boarding.</div>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
