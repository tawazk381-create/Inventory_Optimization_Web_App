// File: public/assets/js/reports.js 

document.addEventListener('DOMContentLoaded', function () {
    const jobIdInput = document.getElementById('jobIdInput');
    const resultsTable = document.getElementById('resultsTable');
    const downloadCsvButton = document.getElementById('downloadCsvButton');
    const downloadJsonButton = document.getElementById('downloadJsonButton');

    // Reset buttons and table
    function resetResults() {
        resultsTable.innerHTML = '';
        downloadCsvButton.disabled = true;
        downloadJsonButton.disabled = true;
    }

    // Fetch job results and populate the table
    function fetchJobResults(jobId) {
        fetch(`/reports/getJobResults?job=${jobId}`)
            .then(response => response.json())
            .then(data => {
                resetResults();

                if (data.error) {
                    alert('Error fetching job results: ' + data.error);
                    return;
                }
                if (!data.results || data.results.length === 0) {
                    resultsTable.innerHTML = '<tr><td colspan="4">No results found for this job.</td></tr>';
                    return;
                }

                // Create table header
                const header = resultsTable.createTHead();
                const row = header.insertRow();
                row.insertCell().innerText = 'Item ID';
                row.insertCell().innerText = 'EOQ';
                row.insertCell().innerText = 'Reorder Point';
                row.insertCell().innerText = 'Safety Stock';

                // Populate table body
                const body = resultsTable.createTBody();
                data.results.forEach(item => {
                    const row = body.insertRow();
                    row.insertCell().innerText = item.item_id;
                    row.insertCell().innerText = item.eoq;
                    row.insertCell().innerText = item.reorder_point;
                    row.insertCell().innerText = item.safety_stock;
                });

                // Enable downloads
                downloadCsvButton.disabled = false;
                downloadJsonButton.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to fetch job results');
                resetResults();
            });
    }

    // Trigger fetch on input change or Enter key
    if (jobIdInput) {
        jobIdInput.addEventListener('change', function () {
            const jobId = jobIdInput.value.trim();
            if (jobId) {
                fetchJobResults(jobId);
            } else {
                resetResults();
            }
        });
        jobIdInput.addEventListener('keyup', function (e) {
            if (e.key === 'Enter') {
                const jobId = jobIdInput.value.trim();
                if (jobId) {
                    fetchJobResults(jobId);
                } else {
                    resetResults();
                }
            }
        });
    }

    // Trigger CSV download
    if (downloadCsvButton) {
        downloadCsvButton.addEventListener('click', function () {
            const jobId = jobIdInput.value.trim();
            if (jobId) {
                window.location.href = `/reports/downloadReport?job=${jobId}&format=csv`;
            } else {
                alert('Please enter a job ID first.');
            }
        });
    }

    // Trigger JSON download
    if (downloadJsonButton) {
        downloadJsonButton.addEventListener('click', function () {
            const jobId = jobIdInput.value.trim();
            if (jobId) {
                window.location.href = `/reports/downloadReport?job=${jobId}&format=json`;
            } else {
                alert('Please enter a job ID first.');
            }
        });
    }
});
