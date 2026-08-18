<div class="modal password-modal" id="passwordModal">
    <div class="modal-content">
        <span class="close-btn" id="closeModal">&times;</span>
        <h2>Change Password</h2>
        
        <div class="success-message" id="successMessage">
            Password changed successfully!
        </div>
        
        <form id="passwordForm">
            <div class="form-group">
                <label for="currentPassword">Current Password</label>
                <input type="password" id="currentPassword" name="currentPassword" required>
                <div class="error-message" id="currentPasswordError"></div>
            </div>
            
            <div class="form-group">
                <label for="newPassword">New Password</label>
                <input type="password" id="newPassword" name="newPassword" required>
                <div class="password-strength">
                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                </div>
                <div class="error-message" id="newPasswordError"></div>
            </div>
            
            <div class="form-group">
                <label for="confirmPassword">Confirm New Password</label>
                <input type="password" id="confirmPassword" name="confirmPassword" required>
                <div class="error-message" id="confirmPasswordError"></div>
            </div>
            
            <button type="submit" class="submit-btn" id="submitBtn">Change Password</button>
        </form>
    </div>
</div>