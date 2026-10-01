(() => {
  function backendUrl(endpoint) {
    const path = window.location.pathname.replace(/\\/g, '/');
    const nestedProjectDepth = (path.match(/\/Drive(?:%20)? Leadership\//gi) || []).length;
    const prefix = nestedProjectDepth > 0 ? '../'.repeat(nestedProjectDepth) : '';
    return `${prefix}backend/api/${endpoint}`;
  }

  async function submitForm(form, endpoint, note, payload) {
    const button = form.querySelector('button[type="submit"]');
    const originalText = button?.textContent || 'Submit';

    if (button) {
      button.disabled = true;
      button.textContent = 'Sending...';
    }
    if (note) note.textContent = 'Sending your information securely...';

    try {
      const csrfResponse = await fetch(backendUrl('public-csrf.php'), { credentials: 'include' });
      const csrfPayload = await csrfResponse.json().catch(() => ({}));
      if (!csrfResponse.ok || !csrfPayload.csrf_token) {
        throw new Error('The form security token could not be loaded. Please refresh and try again.');
      }
      payload._csrf = csrfPayload.csrf_token;

      const response = await fetch(backendUrl(endpoint), {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await response.json().catch(() => ({}));

      if (!response.ok || !result.success) {
        const firstError = result.errors ? Object.values(result.errors)[0] : null;
        throw new Error(firstError || result.message || 'We could not submit your form. Please try again.');
      }

      form.reset();
      if (note) note.textContent = result.message || 'Submitted successfully. We will be in touch shortly.';
    } catch (error) {
      if (note) note.textContent = error.message || 'Submission failed. Please try again later.';
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = originalText;
      }
    }
  }

  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = new FormData(contactForm);
      const note = document.getElementById('form-note') || contactForm.querySelector('.form-note');
      submitForm(contactForm, 'contact-submit.php', note, {
        sender_name: String(data.get('sender_name') || '').trim(),
        sender_email: String(data.get('sender_email') || '').trim(),
        interest: String(data.get('interest') || '').trim(),
        message: String(data.get('message') || '').trim()
      });
    });
  }

  const registrationForm = document.querySelector('.registration-form');
  if (registrationForm) {
    const employmentStatus = registrationForm.querySelector('[name="employment_status"]');
    const employmentOther = registrationForm.querySelector('[name="employment_status_other"]');
    const referralSource = registrationForm.querySelector('[name="referral_source"]');
    const referralOther = registrationForm.querySelector('[name="referral_source_other"]');

    function syncOtherFields() {
      if (employmentOther) employmentOther.required = employmentStatus?.value === 'Other';
      if (referralOther) referralOther.required = referralSource?.value === 'Other';
    }

    employmentStatus?.addEventListener('change', syncOtherFields);
    referralSource?.addEventListener('change', syncOtherFields);
    syncOtherFields();

    registrationForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = new FormData(registrationForm);
      const note = registrationForm.querySelector('.form-note');
      submitForm(registrationForm, 'attendee-submit.php', note, {
        full_name: String(data.get('full_name') || '').trim(),
        organisation: String(data.get('organisation') || '').trim(),
        employment_status: String(data.get('employment_status') || '').trim(),
        employment_status_other: String(data.get('employment_status_other') || '').trim(),
        email: String(data.get('email') || '').trim(),
        phone: String(data.get('phone') || '').trim(),
        is_network_member: String(data.get('is_network_member') || '').trim(),
        referral_source: String(data.get('referral_source') || '').trim(),
        referral_source_other: String(data.get('referral_source_other') || '').trim(),
        expectations: String(data.get('expectations') || '').trim()
      });
    });
  }
})();
