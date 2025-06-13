// Function to insert the ad content
        function insertAd() {
            // Find the target element by ID or name
            const targetElement = document.getElementById('ads') || 
                                  document.getElementsByName('ads')[0];
            
            // Check if the target element exists
            if (targetElement) {
                // Add the 'ads' class for styling
                targetElement.classList.add('ads');
                
                // Create the ad content
                const adContent = `
                    <a href="https://3gp.neocities.org/redirect.html" target="_blank" rel="noopener">
                        <img src="https://3gp.neocities.org/banner.jpg" alt="Banner Ad" class="banner-ad">
                    </a>
                `;
                
                // Insert the ad content
                targetElement.innerHTML = adContent;
                
                console.log('Ad has been inserted successfully!');
            } else {
                console.error('Target element with ID or name "ads" not found!');
            }
        }

        // Run the insertion function when the page loads
        window.addEventListener('DOMContentLoaded', insertAd);