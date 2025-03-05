{{/*
Returns the CDash URL, ex: `http://cdash-example.local`
Use https if `cdash.https` is true, otherwise use http.
*/}}
{{- define "cdash.url" -}}
{{- if .Values.cdash.https -}}
{{-   printf "https://%s" $.Values.cdash.host -}}
{{- else -}}
{{-   printf "http://%s" $.Values.cdash.host -}}
{{- end -}}
{{- end -}}

{{- define "cdash.environment" }}
          - name: "APP_KEY"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-website"
                key: "APP_KEY"
          - name: "AWS_ACCESS_KEY_ID"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-website"
                key: "AWS_ACCESS_KEY_ID"
          - name: "AWS_SECRET_ACCESS_KEY"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-website"
                key: "AWS_SECRET_ACCESS_KEY"
          - name: "DB_PASSWORD"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-website"
                key: "DB_PASSWORD"
          envFrom:
            - configMapRef:
                name: "{{ .Release.Name }}-website"
{{- end }}
