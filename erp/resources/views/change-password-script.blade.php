<script>
    // DOM Elements
    const changePasswordBtn = document.querySelectorAll('.changePasswordBtn');
    const passwordModal = document.getElementById('passwordModal');
    const closeModal = document.getElementById('closeModal');
    const passwordForm = document.getElementById('passwordForm');
    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const submitBtn = document.getElementById('submitBtn');
    const passwordStrengthBar = document.getElementById('passwordStrengthBar');
    const successMessage = document.getElementById('successMessage');
    
    // Error message elements
    const currentPasswordError = document.getElementById('currentPasswordError');
    const newPasswordError = document.getElementById('newPasswordError');
    const confirmPasswordError = document.getElementById('confirmPasswordError');
    
    
    // Open modal
    changePasswordBtn.forEach(btn => {
        btn.addEventListener('click', () => {       
            passwordModal.style.display = 'flex';
            resetForm();
        });
    });
    
    // Close modal
    closeModal.addEventListener('click', () => {
        passwordModal.style.display = 'none';
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === passwordModal) {
            passwordModal.style.display = 'none';
        }
    });
    
    // Password strength indicator
    newPassword.addEventListener('input', () => {
        const password = newPassword.value;
        let strength = 0;
        
        // Check password length
        if (password.length >= 6) strength += 25;
        
        // Check for lowercase letters
        if (/[a-z]/.test(password)) strength += 25;
        
        // Check for uppercase letters
        if (/[A-Z]/.test(password)) strength += 25;
        
        // Check for numbers and special characters
        if (/[0-9]/.test(password) || /[^A-Za-z0-9]/.test(password)) strength += 25;
        
        // Update strength bar
        passwordStrengthBar.style.width = `${strength}%`;
        
        // Update color based on strength
        if (strength < 50) {
            passwordStrengthBar.style.backgroundColor = '#e74c3c'; // Red
        } else if (strength < 75) {
            passwordStrengthBar.style.backgroundColor = '#f39c12'; // Orange
        } else {
            passwordStrengthBar.style.backgroundColor = '#2ecc71'; // Green
        }
    });
    
    // Form validation
    function validateForm() {
        let isValid = true;
        
        // Reset error messages
        resetErrorMessages();
        
        // Validate current password
        if (currentPassword.value.length < 1) {
            currentPasswordError.textContent = 'Current password is required';
            currentPasswordError.style.display = 'block';
            isValid = false;
        }
        
        // Validate new password
        if (newPassword.value.length < 6) {
            newPasswordError.textContent = 'Password must be at least 6 characters';
            newPasswordError.style.display = 'block';
            isValid = false;
        }
        
        // Validate password confirmation
        if (confirmPassword.value !== newPassword.value) {
            confirmPasswordError.textContent = 'Passwords do not match';
            confirmPasswordError.style.display = 'block';
            isValid = false;
        }
        
        return isValid;
    }
    
    // Reset error messages
    function resetErrorMessages() {
        currentPasswordError.style.display = 'none';
        newPasswordError.style.display = 'none';
        confirmPasswordError.style.display = 'none';
    }
    
    // Reset form
    function resetForm() {
        passwordForm.reset();
        resetErrorMessages();
        passwordStrengthBar.style.width = '0';
        successMessage.style.display = 'none';
    }
    
    // Form submission
    passwordForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Changing Password...';
        
        // Prepare form data
        const formData = {
            current_password: currentPassword.value,
            new_password: newPassword.value,
            new_password_confirmation: confirmPassword.value
        };
        
        // Call the AJAX function
        submitPasswordChange(formData);
        
    });
    
    // Real AJAX function (commented out for demonstration)
    function submitPasswordChange(formData) {
        const xhr = new XMLHttpRequest();
        const route = @json(route('role.change.password', ['role' => Str::slug(Auth::user()->getRoleNames()->first())]));
        xhr.open('POST', route, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Show success message
                        successMessage.style.display = 'block';
                        
                        // Hide modal after 2 seconds
                        setTimeout(() => {
                            passwordModal.style.display = 'none';
                        }, 2000);
                    } else {
                        // Show error message
                        if (response.message) {
                            currentPasswordError.textContent = response.message;
                            currentPasswordError.style.display = 'block';
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: "An error occurred. Please try again.",
                                showConfirmButton: true,
                                confirmButtonColor: '#d33',
                                timer: 4000
                            });
                        }
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: "An error occurred. Please try again.",
                        showConfirmButton: true,
                        confirmButtonColor: '#d33',
                        timer: 4000
                    });
                }
                
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Change Password';
            }
        };
        
        xhr.send(JSON.stringify(formData));
    }
</script>