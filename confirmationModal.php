<div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
<div style="background: var(--color-card); padding: 30px; border-radius: 8px; text-align: center; max-width: 400px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); border: 1px solid var(--color-border); color: var(--color-text);">
        <h3 id="modalTitle" style="margin-top: 0; color: var(--primary-color);">Confirm Action</h3>
        <p id="modalMessage">Are you sure you want to proceed?</p>
        <div style="margin-top: 20px;">
            <button id="modalConfirmBtn" class="btn btn-primary">Confirm</button>
            <button id="modalCancelBtn" class="btn btn-secondary" style="margin-left: 10px;">Cancel</button>
        </div>
        <form id="modalActionForm" method="GET" action="" style="display: none;">
            <input type="hidden" name="action" id="modalActionInput">
            <input type="hidden" name="id" id="modalIdInput">
            <input type="hidden" name="cycle_id" id="modalCycleIdInput" value="">
            <input type="hidden" name="applicant_type_id" id="modalApplicantTypeIdInput" value="">
        </form>
    </div>
</div>
</body>

</html>