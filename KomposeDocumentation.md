# Kompose steps

## Generate combined file
1. Execute the `config` command of docker-compose which will print out the YAML that would result after each of the given files are consumed:

```
docker compose -f docker/docker-compose.yml -f docker/docker-compose.mysql.yml -f docker/docker-compose.dev.yml --env-file .env.dev config &> kompose.yml
```

## Make manual Changes 

The output file of the above command has some issues that Kompose will not be able to parse.  To avoid issues, make the following changes manually:

1. Comment out top-level `name:` object

* `#name: cdash`

2. Add a top-level `version` object with the value of "3.8" (the latest at the moment 2023-09-27)

* `version: "3.8"`

3. Ensure all values found under a `ports` attributes which represent numbers are _NOT_ strings

* ++++ : `published: 8080`
* ---- : `published: "8080"`

4. Change `depends_on` to be a list of service names, instead of the current long form.  (No `condition` or `required` attributes)

* ++++ : ```depends_on:
        - chrome
        - database
        - selenium-hub```
* ---- :  ```depends_on:
            chrome:
            condition: service_started
            required: true
            database: <....>

## Execute Kompose on the altered file

Now that the manual changes have been made, execute the `kompose` executable with the new file:

*Note*: `--provider` here could be either `kubernetes` 

```
$ kompose convert --provider kubernetes -f kompose.yml  -v

EBU Checking validation of provider: kubernetes  
DEBU Checking validation of controller:           
DEBU Docker Compose version: 3.8                  
WARN Volume mount on the host "/home/softhat/Work/LANL/CDash" isn't supported - ignoring path on the host 
INFO Network cdash-default is detected at Source, shall be converted to equivalent NetworkPolicy at Destination 
WARN Service "chrome" won't be created because 'ports' is not specified 
WARN Volume mount on the host "/dev/shm" isn't supported - ignoring path on the host 
INFO Network cdash-default is detected at Source, shall be converted to equivalent NetworkPolicy at Destination 
WARN Service "database" won't be created because 'ports' is not specified 
INFO Network cdash-default is detected at Source, shall be converted to equivalent NetworkPolicy at Destination 
INFO Network cdash-default is detected at Source, shall be converted to equivalent NetworkPolicy at Destination 
DEBU Remove duplicate resource: NetworkPolicy/cdash-default 
DEBU Remove duplicate resource: NetworkPolicy/cdash-default 
DEBU Remove duplicate resource: NetworkPolicy/cdash-default 
DEBU Target Dir: .                                
INFO Kubernetes file "cdash-service.yaml" created 
INFO Kubernetes file "selenium-hub-service.yaml" created 
INFO Kubernetes file "cdash-deployment.yaml" created 
INFO Kubernetes file "storage-persistentvolumeclaim.yaml" created 
INFO Kubernetes file "cdash-claim1-persistentvolumeclaim.yaml" created 
INFO Kubernetes file "cdash-default-networkpolicy.yaml" created 
INFO Kubernetes file "chrome-deployment.yaml" created 
INFO Kubernetes file "chrome-claim0-persistentvolumeclaim.yaml" created 
INFO Kubernetes file "database-deployment.yaml" created 
INFO Kubernetes file "mysqldata-persistentvolumeclaim.yaml" created 
INFO Kubernetes file "selenium-hub-deployment.yaml" created 

```

or `openshift`

```

$ kompose convert --provider openshift -f kompose.yml
DEBU Checking validation of provider: openshift   
DEBU Checking validation of controller:           
DEBU Docker Compose version: 3.8                  
WARN Volume mount on the host "/home/softhat/Work/LANL/CDash" isn't supported - ignoring path on the host 
WARN Volume mount on the host "/dev/shm" isn't supported - ignoring path on the host 
DEBU Target Dir: .                                
INFO OpenShift file "cdash-service.yaml" created  
INFO OpenShift file "selenium-hub-service.yaml" created 
INFO OpenShift file "cdash-deploymentconfig.yaml" created 
INFO OpenShift file "cdash-imagestream.yaml" created 
INFO OpenShift file "storage-persistentvolumeclaim.yaml" created 
INFO OpenShift file "cdash-claim1-persistentvolumeclaim.yaml" created 
INFO OpenShift file "chrome-deploymentconfig.yaml" created 
INFO OpenShift file "chrome-imagestream.yaml" created 
INFO OpenShift file "chrome-claim0-persistentvolumeclaim.yaml" created 
INFO OpenShift file "database-deploymentconfig.yaml" created 
INFO OpenShift file "database-imagestream.yaml" created 
INFO OpenShift file "mysqldata-persistentvolumeclaim.yaml" created 
INFO OpenShift file "selenium-hub-deploymentconfig.yaml" created 
INFO OpenShift file "selenium-hub-imagestream.yaml" created 

```