function sendProctorEvent(eventType, message, riskPoints = 3) {

    const formData = new FormData();

    formData.append('csrf_token', csrfToken);
    formData.append('attempt_id', attemptId);
    formData.append('event_type', eventType);
    formData.append('event_message', message);
    formData.append('risk_points', riskPoints);
    formData.append('face_count', 1);
    formData.append('device_id', deviceId);

    fetch(BASE_URL + '/student/proctor_event.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Proctoring:', data);
    })
    .catch(error => {
        console.error('Proctoring error:', error);
    });
}

// Tab switch detection
document.addEventListener('visibilitychange', function () {

    if (document.hidden) {
        sendProctorEvent(
            'tab_switch',
            'Student switched browser tab',
            3
        );
    }
});