# Complete Fix for 413 Upload Errors in Coolify

## THE PROBLEM
You're getting `413 Request Entity Too Large` because there are **TWO** layers blocking large uploads:

1. **Traefik** (Coolify's proxy) - Default limit: ~4MB
2. **Nginx** (inside your container from Nixpacks) - Default limit: 1MB

Both must be fixed!

---

## SOLUTION 1: Fix Traefik (Coolify Proxy)

### Step 1: Add Traefik Middleware
1. In Coolify UI, go to: **Server** → **Proxy** → **Dynamic Configurations**
2. Click **"+ Add"**
3. Name it: `large-uploads`
4. Paste this YAML:

```yaml
http:
  middlewares:
    large-uploads:
      buffering:
        maxRequestBodyBytes: 524288000
        memRequestBodyBytes: 524288000
```

5. Click **Save**

### Step 2: Apply Middleware to Your App
1. Go to your **Application** → **Configuration** → **Advanced**
2. Find **"Labels & Annotations"** section
3. Add this label (replace `YOUR_APP_NAME` with your actual app name):

```
traefik.http.routers.YOUR_APP_NAME.middlewares=large-uploads@file
```

4. **Redeploy** your application

---

## SOLUTION 2: Fix Nginx (Inside Container)

### Option A: Use Dockerfile Instead of Nixpacks

Create `Dockerfile` in your repo:

```dockerfile
FROM serversideup/php:8.3-fpm-nginx

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Create custom nginx config for large uploads
RUN echo 'client_max_body_size 500M;' > /etc/nginx/conf.d/uploads.conf && \
    echo 'client_body_timeout 600s;' >> /etc/nginx/conf.d/uploads.conf && \
    echo 'client_header_timeout 600s;' >> /etc/nginx/conf.d/uploads.conf

# Set PHP upload limits
RUN echo 'upload_max_filesize = 500M' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'post_max_size = 500M' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'max_execution_time = 600' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/uploads.ini

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 80
```

Then in Coolify:
1. Go to **Application** → **General** → **Build Pack**
2. Change from **Nixpacks** to **Dockerfile**
3. **Redeploy**

### Option B: Keep Nixpacks, Fix via Post-Deploy

Your `coolify-post-deploy.sh` should work, but let's verify it runs:

```bash
#!/bin/bash
echo "=== POST-DEPLOY SCRIPT STARTED ==="

# Fix Nginx config
if [ -f /nginx.conf ]; then
    if ! grep -q "client_max_body_size" /nginx.conf; then
        echo "Adding Nginx upload limits..."
        # Find the http block and add config
        sed -i '1 a client_max_body_size 500M;\nclient_body_timeout 600s;\nclient_header_timeout 600s;' /nginx.conf
        nginx -s reload
        echo "✅ Nginx configured"
    fi
fi

echo "=== POST-DEPLOY SCRIPT COMPLETE ==="
```

---

## WHICH SOLUTION TO USE?

### **Recommended: Do BOTH**
1. Fix Traefik (Solution 1) - Takes 2 minutes
2. Switch to Dockerfile (Solution 2A) - Takes 5 minutes, permanent fix

### Why Both?
- Traefik is the first layer (external proxy)
- Nginx is the second layer (inside container)
- Both must allow large uploads

---

## TESTING

After applying both fixes:

1. **Check Traefik**: Your middleware should appear in Dynamic Configurations
2. **Check Nginx**: SSH into container and run:
   ```bash
   nginx -T | grep client_max_body_size
   ```
   Should show: `client_max_body_size 500M;`

3. **Test Upload**: Try uploading your 264MB file

---

## IF IT STILL FAILS

Check these in order:

1. **Is middleware applied?**
   - Check app labels in Coolify UI
   - Should see the middleware label

2. **Is Nginx config correct?**
   - SSH into container: `docker exec -it CONTAINER_ID /bin/bash`
   - Run: `nginx -T | grep client_max_body_size`

3. **Check logs:**
   - Traefik logs: Coolify → Server → Proxy → Logs
   - App logs: Coolify → Application → Logs
   - Look for 413 errors

---

## PERMANENT SOLUTION

**Use the Dockerfile approach (Solution 2A).** This gives you:
- Full control over Nginx config
- No reliance on post-deploy scripts
- Configuration is version-controlled
- Works consistently across deployments

The Dockerfile I provided is production-ready and includes:
- ✅ PHP 8.3 with FPM + Nginx
- ✅ 500MB upload limits (both Nginx and PHP)
- ✅ Proper permissions
- ✅ All necessary timeouts

---

## NEXT STEPS

1. **Right now**: Add Traefik middleware (Solution 1) - 2 minutes
2. **Then**: Create Dockerfile (Solution 2A) - 5 minutes
3. **Test**: Upload a file
4. **Done**: Delete all the debug scripts we created

This is the CORRECT and PERMANENT solution for Coolify.
