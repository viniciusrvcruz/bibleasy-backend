# Production Nginx image for Laravel API
# Since this is an API-only project, we don't need to build frontend assets
FROM nginx:alpine

# Copy custom Nginx configuration
COPY ./docker/production/nginx/nginx.conf /etc/nginx/nginx.conf

# Copy Laravel's public directory
# This includes index.php and any static assets
COPY ./public /var/www/public

# Set the working directory to the public folder
WORKDIR /var/www/public

# Expose port 80 and start Nginx
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
